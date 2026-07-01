<?php

namespace App\Filament\Concerns;

use App\Jobs\ResolveInstrumentSymbolsJob;
use App\Jobs\SyncMarketDataJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

trait RefreshesMarketData
{
    /**
     * Header action that resolves any missing instrument symbols and then pulls
     * fresh prices, dividends and FX rates. Runs synchronously so the page shows
     * up-to-date data right after it reloads.
     */
    protected function refreshMarketDataAction(): Action
    {
        return Action::make('refreshMarketData')
            ->label(__('portfolio.refresh.label'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->action(function () {
                dispatch_sync(new ResolveInstrumentSymbolsJob);
                dispatch_sync(new SyncMarketDataJob);

                Notification::make()
                    ->title(__('portfolio.refresh.done'))
                    ->success()
                    ->send();

                return redirect(static::getUrl());
            });
    }
}
