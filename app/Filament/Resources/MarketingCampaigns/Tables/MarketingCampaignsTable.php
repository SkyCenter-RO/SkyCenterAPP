<?php

namespace App\Filament\Resources\MarketingCampaigns\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MarketingCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('platform')
                    ->badge()
                    ->sortable(),
                TextColumn::make('vertical')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'    => 'success',
                        'paused'    => 'warning',
                        'completed' => 'gray',
                        'draft'     => 'info',
                        default     => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('period_month')
                    ->date('M Y')
                    ->sortable(),
                TextColumn::make('budget_eur')
                    ->money('EUR')
                    ->label('Buget'),
                TextColumn::make('spend_eur')
                    ->money('EUR')
                    ->label('Cheltuit'),
                TextColumn::make('roas')
                    ->label('ROAS')
                    ->suffix('x')
                    ->sortable(),
                TextColumn::make('conversions')
                    ->label('Conv.')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('platform')
                    ->options([
                        'google'    => 'Google',
                        'facebook'  => 'Facebook',
                        'instagram' => 'Instagram',
                        'tiktok'    => 'TikTok',
                        'bing'      => 'Bing',
                    ]),
                SelectFilter::make('vertical')
                    ->options([
                        'parcare' => 'Parcare',
                        'hotel'   => 'Hotel',
                        'rent'    => 'Rent-a-car',
                        'bundle'  => 'Bundle',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'active'    => 'Activ',
                        'paused'    => 'Pauzat',
                        'completed' => 'Finalizat',
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
            ->defaultSort('period_month', 'desc');
    }
}
