<?php

namespace App\Filament\Resources\ParkingPrices\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ParkingPriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('source')
                    ->required()
                    ->default('manual'),
                TextInput::make('external_id'),
                TextInput::make('vehicle_type')
                    ->required(),
                TextInput::make('min_days')
                    ->numeric(),
                TextInput::make('max_days')
                    ->numeric(),
                TextInput::make('price_per_day')
                    ->numeric(),
                TextInput::make('fixed_price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('currency')
                    ->required()
                    ->default('RON'),
                TextInput::make('metadata'),
            ]);
    }
}
