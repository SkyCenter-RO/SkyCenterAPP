<?php

namespace App\Filament\Resources\ParkingReservations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ParkingReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source')
                    ->searchable(),
                TextColumn::make('external_id')
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->searchable(),
                TextColumn::make('lot.name')
                    ->searchable(),
                TextColumn::make('zone.id')
                    ->searchable(),
                TextColumn::make('parkingSpace.id')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('plate')
                    ->searchable(),
                TextColumn::make('normalized_plate')
                    ->searchable(),
                TextColumn::make('vehicle_type')
                    ->searchable(),
                TextColumn::make('check_in_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('check_out_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('days')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('adults')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('children')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('keys_left')
                    ->boolean(),
                TextColumn::make('cost')
                    ->money()
                    ->sortable(),
                TextColumn::make('quoted_price')
                    ->money()
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable(),
                IconColumn::make('paid')
                    ->boolean(),
                IconColumn::make('review_request_sent')
                    ->boolean(),
                TextColumn::make('source_created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_by_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_by_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
