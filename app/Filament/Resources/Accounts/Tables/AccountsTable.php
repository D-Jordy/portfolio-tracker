<?php

namespace App\Filament\Resources\Accounts\Tables;

use App\Actions\ImportBrokerCsv;
use App\Models\Account;
use App\Services\Import\ImportResult;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('accounts.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('broker')
                    ->label(__('accounts.fields.broker'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                TextColumn::make('import_watermark')
                    ->label(__('accounts.fields.last_import'))
                    ->date('d-m-Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->recordActions([
                self::importAction(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    private static function importAction(): Action
    {
        return Action::make('import')
            ->label(__('accounts.import.label'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading(__('accounts.import.heading'))
            ->modalDescription(__('accounts.import.description'))
            ->modalSubmitActionLabel(__('accounts.import.submit'))
            ->schema([
                FileUpload::make('transactions_csv')
                    ->label(__('accounts.import.transactions.label'))
                    ->helperText(__('accounts.import.transactions.helper'))
                    ->disk('local')
                    ->directory('imports/transactions')
                    ->acceptedFileTypes(['text/csv', 'text/plain']),
                FileUpload::make('account_csv')
                    ->label(__('accounts.import.account.label'))
                    ->helperText(__('accounts.import.account.helper'))
                    ->disk('local')
                    ->directory('imports/account')
                    ->acceptedFileTypes(['text/csv', 'text/plain']),
            ])
            ->action(function (Account $record, array $data): void {
                $importer = new ImportBrokerCsv;

                /** @var array<string, ImportResult> $results */
                $results = [];

                if (filled($data['transactions_csv'] ?? null)) {
                    $results[__('accounts.import.group_transactions')] = $importer->transactions($record, $data['transactions_csv']);
                }

                if (filled($data['account_csv'] ?? null)) {
                    $results[__('accounts.import.group_account')] = $importer->account($record, $data['account_csv']);
                }

                self::notify($results);
            });
    }

    /**
     * @param  array<string, ImportResult>  $results
     */
    private static function notify(array $results): void
    {
        if ($results === []) {
            Notification::make()
                ->title(__('accounts.import.no_file'))
                ->warning()
                ->send();

            return;
        }

        $lines = collect($results)->map(
            fn (ImportResult $result, string $label): string => __('accounts.import.result_line', [
                'label' => $label,
                'inserted' => $result->inserted,
                'skipped' => $result->skipped,
            ])
        );

        $hasErrors = collect($results)->contains(fn (ImportResult $result): bool => $result->hasErrors());

        Notification::make()
            ->title($hasErrors ? __('accounts.import.done_errors') : __('accounts.import.done'))
            ->body($lines->implode("\n"))
            ->status($hasErrors ? 'warning' : 'success')
            ->send();
    }
}
