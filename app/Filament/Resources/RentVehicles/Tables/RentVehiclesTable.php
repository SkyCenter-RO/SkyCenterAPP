<?php

namespace App\Filament\Resources\RentVehicles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RentVehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source')
                    ->searchable(),
                TextColumn::make('external_id')
                    ->searchable(),
                TextColumn::make('license_plate')
                    ->searchable(),
                TextColumn::make('chassis_vin')
                    ->searchable(),
                TextColumn::make('brand')
                    ->searchable(),
                TextColumn::make('model_name')
                    ->searchable(),
                TextColumn::make('manufacture_year')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tire_type')
                    ->searchable(),
                TextColumn::make('insurance_start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('insurance_end_date')
                    ->date()
                    ->sortable(),
                IconColumn::make('insurance_12_months')
                    ->boolean(),
                TextColumn::make('itp_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('itp_expiry_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('current_km')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('monthly_rent_price')
                    ->money()
                    ->sortable(),
                TextColumn::make('daily_rent_price')
                    ->money()
                    ->sortable(),
                TextColumn::make('warranty_standard')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
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
