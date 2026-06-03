<?php

namespace App\Actions;

use App\Models\CashMovement;
use App\Models\Dividend;
use App\Models\FxRate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ComputeIncomingDividends
{
    // Frequency buckets: median gap in days → annual pay count.
    private const FREQUENCY_BUCKETS = [
        ['max' => 45,  'times' => 12], // monthly
        ['max' => 135, 'times' => 4],  // quarterly
        ['max' => 270, 'times' => 2],  // semi-annual
        ['max' => PHP_INT_MAX, 'times' => 1], // annual
    ];

    public function __construct(private ComputePortfolio $portfolio) {}

    /**
     * Build the dividend income forecast for a user.
     *
     * @return array{
     *   events: array<int, array>,
     *   monthly: array<int, array{month: string, expected_eur: float}>,
     *   summary: array{next_12m_total_eur: float, trailing_12m_received_eur: float, instrument_count: int},
     * }
     */
    public function forUser(User $user): array
    {
        $accountIds = $user->accounts()->pluck('id');

        // Resolve current open positions: [instrument_id => quantity].
        $positions    = $this->portfolio->forUser($user)['positions'];
        $qtyByInstrument = collect($positions)
            ->filter(fn($p) => ($p['quantity'] ?? 0) > 0)
            ->keyBy('instrument_id')
            ->map(fn($p) => (float) $p['quantity']);

        if ($qtyByInstrument->isEmpty()) {
            return $this->emptyResult($accountIds);
        }

        // Load all stored historical dividends for held instruments, grouped by instrument.
        $instrumentIds = $qtyByInstrument->keys()->all();
        $allDividends  = Dividend::whereIn('instrument_id', $instrumentIds)
            ->orderBy('ex_date')
            ->with('instrument')
            ->get()
            ->groupBy('instrument_id');

        // Build forward event list.
        $events      = [];
        $today       = now()->startOfDay();
        $horizon     = now()->addMonths(12)->endOfDay();
        $currencies  = collect();

        foreach ($qtyByInstrument as $instrumentId => $qty) {
            $history = $allDividends->get($instrumentId);

            if (!$history || $history->count() < 2) {
                // Cannot infer cadence — skip.
                continue;
            }

            $projected = $this->projectEvents($history, $qty, $today, $horizon);
            $events    = array_merge($events, $projected);

            if (!empty($projected)) {
                $currencies->push($projected[0]['currency']);
            }
        }

        // Sort all events by ex_date ascending.
        usort($events, fn($a, $b) => $a['ex_date'] <=> $b['ex_date']);

        // Pre-load trailing cash movements so we can include their currencies in the FX lookup.
        $trailingRows = $this->rawTrailingRows($accountIds);

        // Collect all currencies needed: forecast events + trailing cash movements.
        $uniqueCurrencies = $currencies
            ->merge(collect($events)->pluck('currency'))
            ->merge($trailingRows->pluck('currency'))
            ->unique()
            ->filter(fn($c) => $c !== 'EUR')
            ->values();
        $fxRates = $this->latestFxRatesFor($uniqueCurrencies);

        $next12mTotal = 0.0;

        foreach ($events as &$event) {
            $event['expected_eur'] = $this->toEur(
                $event['quantity'] * (float) $event['amount_per_share'],
                $event['currency'],
                $fxRates
            );

            if ($event['expected_eur'] !== null) {
                $next12mTotal += $event['expected_eur'];
            }
        }
        unset($event);

        // Aggregate into zero-filled monthly buckets (next 12 months).
        $monthly = $this->buildMonthlyBuckets($events, $today);

        // Trailing 12-month net received (dividends net of withholding tax, EUR).
        $trailing = $this->trailingReceivedEur($trailingRows, $fxRates);

        $summary = [
            'next_12m_total_eur'        => round($next12mTotal, 2),
            'trailing_12m_received_eur' => round($trailing, 2),
            'instrument_count'          => count(array_unique(array_column($events, 'instrument_id'))),
        ];

        return compact('events', 'monthly', 'summary');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Project forward dividend events for one instrument over the given horizon.
     * Requires at least 2 historical ex-dates to infer cadence.
     */
    private function projectEvents(
        Collection $history,
        float $qty,
        Carbon $today,
        Carbon $horizon,
    ): array {
        $exDates = $history->pluck('ex_date')->map(fn($d) => Carbon::parse($d))->sortBy(fn($d) => $d->timestamp)->values();

        if ($exDates->count() < 2) {
            return [];
        }

        // Median gap in days from the most recent ex-dates (up to last 8).
        $recent = $exDates->slice(-8)->values();
        $gaps   = [];

        for ($i = 1; $i < $recent->count(); $i++) {
            $gaps[] = $recent[$i - 1]->diffInDays($recent[$i]);
        }

        sort($gaps);
        $medianGap = $this->median($gaps);

        if ($medianGap < 7) {
            // Degenerate data — skip.
            return [];
        }

        $timesPerYear = $this->gapToFrequency($medianGap);
        $intervalDays = (int) round(365 / $timesPerYear);

        // Latest amount and currency from the most recent historical row.
        $latest   = $history->sortBy('ex_date')->last();
        $amount   = (float) $latest->amount_per_share;
        $currency = $latest->currency;
        $instrument = $latest->instrument;

        // Walk forward from the last historical ex_date.
        $cursor   = Carbon::parse($latest->ex_date);
        $events   = [];

        while (true) {
            $cursor->addDays($intervalDays);

            if ($cursor->gt($horizon)) {
                break;
            }

            if ($cursor->gte($today)) {
                $events[] = [
                    'instrument_id'    => $instrument->id,
                    'name'             => $instrument->name,
                    'yahoo_symbol'     => $instrument->yahoo_symbol,
                    'ex_date'          => $cursor->toDateString(),
                    'pay_date'         => null,
                    'amount_per_share' => round($amount, 8),
                    'currency'         => $currency,
                    'quantity'         => round($qty, 4),
                    'expected_eur'     => null, // filled in later
                    'projected'        => true,
                ];
            }
        }

        return $events;
    }

    private function median(array $sorted): float
    {
        $count = count($sorted);

        if ($count === 0) {
            return 0.0;
        }

        $mid = (int) ($count / 2);

        return $count % 2 === 1
            ? (float) $sorted[$mid]
            : ($sorted[$mid - 1] + $sorted[$mid]) / 2.0;
    }

    private function gapToFrequency(float $medianGap): int
    {
        foreach (self::FREQUENCY_BUCKETS as $bucket) {
            if ($medianGap <= $bucket['max']) {
                return $bucket['times'];
            }
        }

        return 1;
    }

    private function toEur(float $amount, string $currency, Collection $fxRates): ?float
    {
        if ($currency === 'EUR') {
            return round($amount, 2);
        }

        $fx = $fxRates->get($currency);

        if (!$fx) {
            return null;
        }

        return round($amount * (float) $fx->rate_to_eur, 2);
    }

    /** Latest FX rate per currency — uses a portable MAX(date) subquery (works with SQLite and Postgres). */
    private function latestFxRatesFor(Collection $currencies): Collection
    {
        if ($currencies->isEmpty()) {
            return collect();
        }

        $latest = FxRate::query()
            ->select('currency', DB::raw('MAX(date) as max_date'))
            ->whereIn('currency', $currencies)
            ->groupBy('currency');

        return FxRate::query()
            ->joinSub($latest, 'latest', function ($join) {
                $join->on('fx_rates.currency', '=', 'latest.currency')
                     ->on('fx_rates.date', '=', 'latest.max_date');
            })
            ->whereIn('fx_rates.currency', $currencies)
            ->select('fx_rates.currency', 'fx_rates.rate_to_eur')
            ->get()
            ->keyBy('currency');
    }

    /**
     * Zero-filled monthly buckets for the next 12 calendar months,
     * summing expected_eur from events into the appropriate bucket.
     */
    private function buildMonthlyBuckets(array $events, Carbon $today): array
    {
        $buckets = [];

        for ($i = 0; $i < 12; $i++) {
            $buckets[$today->copy()->addMonths($i)->format('Y-m')] = 0.0;
        }

        foreach ($events as $event) {
            $month = substr($event['ex_date'], 0, 7); // 'YYYY-MM'

            if (array_key_exists($month, $buckets) && $event['expected_eur'] !== null) {
                $buckets[$month] += $event['expected_eur'];
            }
        }

        return array_map(
            fn($month, $total) => ['month' => $month, 'expected_eur' => round($total, 2)],
            array_keys($buckets),
            array_values($buckets),
        );
    }

    /** Raw trailing-12m dividend + withholding_tax cash movement rows for FX currency collection. */
    private function rawTrailingRows(mixed $accountIds): Collection
    {
        if ($accountIds->isEmpty()) {
            return collect();
        }

        return CashMovement::whereIn('account_id', $accountIds)
            ->whereIn('type', ['dividend', 'withholding_tax'])
            ->where('occurred_at', '>=', now()->subYear())
            ->select('amount', 'currency')
            ->get();
    }

    /**
     * Net dividend income received in the last 12 months, converted to EUR.
     * Mirrors the math in ComputePortfolio::dividendsEurByInstrument().
     */
    private function trailingReceivedEur(Collection $rows, Collection $fxRates): float
    {
        $total = 0.0;

        foreach ($rows as $row) {
            $amount = (float) $row->amount;

            if ($row->currency === 'EUR') {
                $total += $amount;
            } else {
                $fx = $fxRates->get($row->currency);
                $total += $fx ? $amount * (float) $fx->rate_to_eur : 0.0;
            }
        }

        return $total;
    }

    private function emptyResult(mixed $accountIds): array
    {
        $trailingRows = $this->rawTrailingRows($accountIds);
        $trailingCurrencies = $trailingRows->pluck('currency')->unique()->filter(fn($c) => $c !== 'EUR')->values();
        $fxRates = $this->latestFxRatesFor($trailingCurrencies);

        return [
            'events'  => [],
            'monthly' => array_map(
                fn($i) => ['month' => now()->addMonths($i)->format('Y-m'), 'expected_eur' => 0.0],
                range(0, 11)
            ),
            'summary' => [
                'next_12m_total_eur'        => 0.0,
                'trailing_12m_received_eur' => round($this->trailingReceivedEur($trailingRows, $fxRates), 2),
                'instrument_count'          => 0,
            ],
        ];
    }
}
