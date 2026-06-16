<?php

namespace App\Filament\Resources\RentMaintenanceRecords\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RentMaintenanceRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('rent_vehicle_id')
                    ->required()
                    ->numeric(),
                DateTimePicker::make('service_at'),
                TextInput::make('mileage_at_service')
                    ->numeric(),
                TextInput::make('intervention_type'),
                TextInput::make('next_service_km')
                    ->numeric(),
                Textarea::make('details')
                    ->columnSpanFull(),
                TextInput::make('metadata'),
            ]);
    }
}
