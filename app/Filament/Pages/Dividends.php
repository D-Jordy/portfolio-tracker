<?php

namespace App\Filament\Pages;

use App\Actions\ComputeIncomingDividends;
use App\Filament\Concerns\RefreshesMarketData;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Dividends extends Page
{
    use RefreshesMarketData;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('dividends.nav');
    }

    protected string $view = 'filament.pages.dividends';

    /** @var array<int, array<string, mixed>> */
    public array $confirmed = [];

    /** @var array<int, array<string, mixed>> */
    public array $projected = [];

    /** @var array<string, mixed> */
    public array $summary = [];

    public function mount(ComputeIncomingDividends $compute): void
    {
        ['confirmed' => $confirmed, 'events' => $events, 'summary' => $summary] = $compute->forUser(auth()->user());

        $this->confirmed = $confirmed;
        $this->projected = $events;
        $this->summary = $summary;
    }

    public function getTitle(): string
    {
        return __('dividends.title');
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [$this->refreshMarketDataAction()];
    }
}
