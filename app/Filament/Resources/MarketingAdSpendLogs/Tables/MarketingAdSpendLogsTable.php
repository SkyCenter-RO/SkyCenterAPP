<?php

namespace App\Filament\Resources\MarketingAdSpendLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MarketingAdSpendLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('spent_on')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('platform')
                    ->badge()
                    ->sortable(),
                TextColumn::make('vertical')
                    ->badge()
                    ->sortable(),
                TextColumn::make('campaign.name')
                    ->label('Campanie')
                    ->searchable(),
                TextColumn::make('amount_eur')
                    ->money('EUR')
                    ->label('Sumă')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('platform')
                    ->options([
                        'google'    => 'Google',
                        'facebook'  => 'Facebook',
                        'instagram' => 'Instagram',
                        'tiktok'    => 'TikTok',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('spent_on', 'desc');
    }
}
