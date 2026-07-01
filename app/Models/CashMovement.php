<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use HasFactory;

    /**
     * Stable idempotency key for a cash movement, built from normalised fields
     * so it is identical whether computed at import time (from a CSV row) or
     * recomputed later from a stored row. Robust to raw-formatting differences
     * between re-exports (decimals, spacing, currency casing).
     */
    public static function makeDedupeHash(
        Carbon $occurredAt,
        string $description,
        int|float|string $amount,
        string $currency,
    ): string {
        return hash('sha256', implode('|', [
            $occurredAt->format('Y-m-d H:i'),
            trim($description),
            number_format((float) $amount, 8, '.', ''),
            strtoupper(trim($currency)),
        ]));
    }

    protected $fillable = [
        'account_id', 'instrument_id', 'occurred_at', 'value_date',
        'type', 'amount', 'currency', 'fx_rate', 'balance_eur',
        'description', 'excluded_from_returns', 'source', 'dedupe_hash',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'value_date' => 'date',
        'amount' => 'decimal:8',
        'fx_rate' => 'decimal:8',
        'balance_eur' => 'decimal:4',
        'excluded_from_returns' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
