<?php

namespace App\Filament\Resources\RentVehicles\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RentVehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('source')
                    ->required()
                    ->default('manual'),
                TextInput::make('external_id'),
                TextInput::make('license_plate'),
                TextInput::make('chassis_vin'),
                TextInput::make('brand'),
                TextInput::make('model_name'),
                TextInput::make('manufacture_year')
                    ->numeric(),
                TextInput::make('tire_type'),
                DatePicker::make('insurance_start_date'),
                DatePicker::make('insurance_end_date'),
                Toggle::make('insurance_12_months')
                    ->required(),
                DatePicker::make('itp_date'),
                DatePicker::make('itp_expiry_date'),
                TextInput::make('current_km')
                    ->numeric(),
                TextInput::make('monthly_rent_price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('daily_rent_price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('warranty_standard')
                    ->numeric(),
                TextInput::make('currency')
                    ->required()
                    ->default('RON'),
                TextInput::make('status')
                    ->required()
                    ->default('available'),
                Textarea::make('notes')
                    ->columnSpanFull(),
                TextInput::make('metadata'),
            ]);
    }
}
