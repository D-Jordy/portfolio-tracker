<?php

namespace App\Filament\Resources\Accounts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('accounts.fields.name'))
                    ->required()
                    ->maxLength(100),
                Select::make('broker')
                    ->label(__('accounts.fields.broker'))
                    ->options(['degiro' => 'DEGIRO'])
                    ->default('degiro')
                    ->required(),
            ]);
    }
}
