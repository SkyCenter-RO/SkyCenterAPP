<?php

namespace App\Filament\Resources\MarketingReviews\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MarketingReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recorded_on')
                    ->date('d.m.Y')
                    ->sortable()
                    ->label('Data înregistrării'),
                TextColumn::make('platform')
                    ->badge()
                    ->sortable()
                    ->label('Platformă'),
                TextColumn::make('vertical')
                    ->badge()
                    ->sortable()
                    ->label('Verticală'),
                TextColumn::make('score')
                    ->numeric()
                    ->sortable()
                    ->label('Scor'),
                TextColumn::make('review_count')
                    ->numeric()
                    ->sortable()
                    ->label('Recenzii'),
                TextColumn::make('notes')
                    ->limit(50)
                    ->label('Note'),
            ])
            ->filters([
                SelectFilter::make('platform')
                    ->options([
                        'google' => 'Google',
                        'booking' => 'Booking.com',
                        'facebook' => 'Facebook',
                        'tripadvisor' => 'TripAdvisor',
                        'airbnb' => 'Airbnb',
                    ]),
                SelectFilter::make('vertical')
                    ->options([
                        'hotel' => 'Hotel',
                        'parcare' => 'Parcare',
                        'rent' => 'Rent-a-car',
                        'all' => 'Toate',
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
            ->defaultSort('recorded_on', 'desc');
    }
}
