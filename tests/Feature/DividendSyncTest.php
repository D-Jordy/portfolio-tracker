<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Services\MarketData\DividendSyncService;
use App\Services\MarketData\YahooFinanceAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DividendSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_inserts_dividend_rows_for_instrument(): void
    {
        $instrument = Instrument::factory()->create(['yahoo_symbol' => 'AAPL']);

        $this->mock(YahooFinanceAdapter::class, function ($mock) {
            $mock->shouldReceive('dividends')
                ->once()
                ->andReturn([
                    ['ex_date' => '2023-02-10', 'amount' => 0.23, 'currency' => 'USD'],
                    ['ex_date' => '2023-05-12', 'amount' => 0.24, 'currency' => 'USD'],
                    ['ex_date' => '2023-08-11', 'amount' => 0.24, 'currency' => 'USD'],
                ]);
        });

        $rows = app(DividendSyncService::class)->syncInstrument($instrument);

        $this->assertSame(3, $rows);
        $this->assertDatabaseCount('dividends', 3);
        $this->assertDatabaseHas('dividends', [
            'instrument_id'    => $instrument->id,
            'ex_date'          => '2023-02-10',
            'amount_per_share' => '0.23000000',
            'currency'         => 'USD',
        ]);
    }

    public function test_gbp_pence_amounts_are_divided_by_100(): void
    {
        $instrument = Instrument::factory()->create(['yahoo_symbol' => 'SHEL.L']);

        $this->mock(YahooFinanceAdapter::class, function ($mock) {
            $mock->shouldReceive('dividends')
                ->once()
                ->andReturn([
                    ['ex_date' => '2023-03-09', 'amount' => 28.8, 'currency' => 'GBp'],
                ]);
        });

        app(DividendSyncService::class)->syncInstrument($instrument);

        $this->assertDatabaseHas('dividends', [
            'instrument_id'    => $instrument->id,
            'amount_per_share' => '0.28800000',
            'currency'         => 'GBP',
        ]);
    }

    public function test_sync_is_idempotent(): void
    {
        $instrument = Instrument::factory()->create(['yahoo_symbol' => 'MSFT']);

        $rows = [
            ['ex_date' => '2023-08-16', 'amount' => 0.68, 'currency' => 'USD'],
        ];

        $this->mock(YahooFinanceAdapter::class, function ($mock) use ($rows) {
            $mock->shouldReceive('dividends')
                ->twice()
                ->andReturn($rows);
        });

        $service = app(DividendSyncService::class);
        $service->syncInstrument($instrument);
        $service->syncInstrument($instrument);

        // Second run should resume from the next day after the stored ex_date,
        // but the mock always returns the same row — upsert must not duplicate.
        $this->assertDatabaseCount('dividends', 1);
    }

    public function test_instrument_without_yahoo_symbol_is_skipped(): void
    {
        $instrument = Instrument::factory()->create(['yahoo_symbol' => null]);

        $this->mock(YahooFinanceAdapter::class, function ($mock) {
            $mock->shouldNotReceive('dividends');
        });

        $rows = app(DividendSyncService::class)->syncInstrument($instrument);

        $this->assertSame(0, $rows);
        $this->assertDatabaseCount('dividends', 0);
    }
}
