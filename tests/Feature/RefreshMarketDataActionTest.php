<?php

namespace Tests\Feature;

use App\Filament\Pages\Dividends;
use App\Filament\Pages\Portfolio;
use App\Jobs\ResolveInstrumentSymbolsJob;
use App\Jobs\SyncMarketDataJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class RefreshMarketDataActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_portfolio_refresh_action_resolves_symbols_and_syncs_market_data(): void
    {
        Bus::fake();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Portfolio::class)
            ->callAction('refreshMarketData');

        Bus::assertDispatchedSync(ResolveInstrumentSymbolsJob::class);
        Bus::assertDispatchedSync(SyncMarketDataJob::class);
    }

    public function test_dividends_page_exposes_the_same_refresh_action(): void
    {
        Bus::fake();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Dividends::class)
            ->callAction('refreshMarketData');

        Bus::assertDispatchedSync(ResolveInstrumentSymbolsJob::class);
        Bus::assertDispatchedSync(SyncMarketDataJob::class);
    }
}
