<?php

namespace App\Filament\Resources\ParkingCustomers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ParkingCustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('source')
                    ->required()
                    ->default('manual'),
                TextInput::make('external_id'),
                TextInput::make('name'),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('normalized_phone')
                    ->tel(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('city'),
                TextInput::make('metadata'),
            ]);
    }
}
