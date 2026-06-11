<?php

namespace App\Filament\Resources\MarketingChannels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MarketingChannelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nume canal'),
                TextColumn::make('channel_type')
                    ->badge()
                    ->sortable()
                    ->label('Tip canal'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'setup_needed' => 'warning',
                        'paused' => 'gray',
                        'monitoring' => 'info',
                        'blocked' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->label('Status'),
                TextColumn::make('monthly_budget_eur')
                    ->money('EUR')
                    ->sortable()
                    ->label('Buget lunar'),
                TextColumn::make('last_reviewed_at')
                    ->date('d.m.Y')
                    ->sortable()
                    ->label('Ultima revizuire'),
            ])
            ->filters([
                SelectFilter::make('channel_type')
                    ->options([
                        'ads' => 'Ads / Paid',
                        'seo' => 'SEO / Organic Search',
                        'social' => 'Social Media',
                        'listing' => 'Listing / Directory',
                        'affiliate' => 'Affiliate',
                        'email' => 'Email Marketing',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Activ',
                        'setup_needed' => 'Necesită configurare',
                        'paused' => 'Pauzat',
                        'monitoring' => 'Monitorizare',
                        'blocked' => 'Blocat',
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
            ->defaultSort('name', 'asc');
    }
}
