<?php

namespace App\Filament\Resources\ParkingReservations\Schemas;

use App\Enums\ParkingReservationStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ParkingReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('source')
                    ->required()
                    ->default('manual'),
                TextInput::make('external_id'),
                Select::make('customer_id')
                    ->relationship('customer', 'name'),
                Select::make('lot_id')
                    ->relationship('lot', 'name'),
                Select::make('zone_id')
                    ->relationship('zone', 'id'),
                Select::make('parking_space_id')
                    ->relationship('parkingSpace', 'id'),
                Select::make('status')
                    ->options(ParkingReservationStatus::class)
                    ->required()
                    ->default(ParkingReservationStatus::PENDING_APPROVAL),
                TextInput::make('plate'),
                TextInput::make('normalized_plate'),
                TextInput::make('vehicle_type'),
                DateTimePicker::make('check_in_at'),
                DateTimePicker::make('check_out_at'),
                TextInput::make('days')
                    ->numeric(),
                TextInput::make('adults')
                    ->numeric(),
                TextInput::make('children')
                    ->numeric(),
                Toggle::make('keys_left')
                    ->required(),
                TextInput::make('cost')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('quoted_price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('currency')
                    ->required()
                    ->default('RON'),
                Toggle::make('paid')
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                Toggle::make('review_request_sent')
                    ->required(),
                DateTimePicker::make('source_created_at'),
                TextInput::make('metadata'),
                TextInput::make('created_by_id')
                    ->numeric(),
                TextInput::make('updated_by_id')
                    ->numeric(),
            ]);
    }
}
