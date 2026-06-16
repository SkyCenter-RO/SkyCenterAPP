<?php

namespace App\Filament\Resources\BudgetTransactions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BudgetTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('source')
                    ->required()
                    ->default('manual'),
                TextInput::make('external_id'),
                TextInput::make('type')
                    ->required(),
                Select::make('category_id')
                    ->relationship('category', 'name'),
                TextInput::make('service'),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('currency')
                    ->required()
                    ->default('RON'),
                DatePicker::make('occurred_on')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('telegram_chat')
                    ->tel(),
                Select::make('raw_message_id')
                    ->relationship('rawMessage', 'id'),
                TextInput::make('metadata'),
                TextInput::make('created_by_id')
                    ->numeric(),
            ]);
    }
}
