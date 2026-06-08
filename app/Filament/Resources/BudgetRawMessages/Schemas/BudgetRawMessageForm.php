<?php

namespace App\Filament\Resources\BudgetRawMessages\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BudgetRawMessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('chat_id')
                    ->required(),
                TextInput::make('message_id')
                    ->required(),
                Textarea::make('text')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('parsed')
                    ->required(),
                TextInput::make('transaction_id')
                    ->numeric(),
                DateTimePicker::make('received_at')
                    ->required(),
            ]);
    }
}
