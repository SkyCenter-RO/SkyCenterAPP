<?php

namespace App\Filament\Resources\LodgingReservations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class LodgingReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('source')
                    ->required()
                    ->default('manual'),
                TextInput::make('external_id'),
                Select::make('room_id')
                    ->relationship('room', 'name'),
                TextInput::make('guest_name'),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('normalized_phone')
                    ->tel(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('status'),
                DatePicker::make('check_in'),
                DatePicker::make('check_out'),
                TextInput::make('nights')
                    ->numeric(),
                TextInput::make('price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('direct_price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('currency')
                    ->required()
                    ->default('RON'),
                DateTimePicker::make('source_created_at'),
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
