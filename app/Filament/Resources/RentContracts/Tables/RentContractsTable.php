<?php

namespace App\Filament\Resources\RentContracts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RentContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source')
                    ->searchable(),
                TextColumn::make('external_id')
                    ->searchable(),
                TextColumn::make('contract_code')
                    ->searchable(),
                TextColumn::make('rent_vehicle_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rent_client_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('usage_type')
                    ->searchable(),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('km_at_handover')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('km_at_return')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('daily_price')
                    ->money()
                    ->sortable(),
                TextColumn::make('monthly_price')
                    ->money()
                    ->sortable(),
                TextColumn::make('warranty_collected')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_price')
                    ->money()
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
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
