<?php

namespace App\Filament\Resources\ParkingReservations\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusAuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'statusAudits';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('to_status')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('to_status')
            ->columns([
                TextColumn::make('to_status')
                    ->searchable(),
            ])
            ->filters([
                //
            ]);
    }
}
