<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('source')
                    ->required()
                    ->default('manual'),
                TextInput::make('external_id'),
                TextInput::make('service')
                    ->required(),
                Select::make('parking_reservation_id')
                    ->relationship('parkingReservation', 'id'),
                Select::make('lodging_reservation_id')
                    ->relationship('lodgingReservation', 'id'),
                Select::make('rent_contract_id')
                    ->relationship('rentContract', 'id'),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('currency')
                    ->required()
                    ->default('RON'),
                TextInput::make('method')
                    ->required(),
                DateTimePicker::make('paid_at'),
                Textarea::make('notes')
                    ->columnSpanFull(),
                TextInput::make('metadata'),
                TextInput::make('created_by_id')
                    ->numeric(),
                TextInput::make('updated_by_id')
                    ->numeric(),
            ]);
    }
}
