<?php

namespace App\Filament\Resources\MarketingContentCalendar\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MarketingContentCalendarTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scheduled_at')
                    ->date('d.m.Y')
                    ->sortable()
                    ->label('Data programată'),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->label('Titlu'),
                TextColumn::make('platform')
                    ->badge()
                    ->sortable()
                    ->label('Platformă'),
                TextColumn::make('content_type')
                    ->badge()
                    ->sortable()
                    ->label('Tip conținut'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'idea' => 'gray',
                        'in_progress' => 'warning',
                        'ready' => 'info',
                        'scheduled' => 'primary',
                        'published' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->label('Status'),
                TextColumn::make('published_at')
                    ->date('d.m.Y')
                    ->sortable()
                    ->label('Data publicării'),
            ])
            ->filters([
                SelectFilter::make('platform')
                    ->options([
                        'facebook' => 'Facebook',
                        'instagram' => 'Instagram',
                        'tiktok' => 'TikTok',
                        'all' => 'Toate',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'idea' => 'Idee',
                        'in_progress' => 'În lucru',
                        'ready' => 'Pregătită',
                        'scheduled' => 'Programată',
                        'published' => 'Publicată',
                        'cancelled' => 'Anulată',
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
            ->defaultSort('scheduled_at', 'desc');
    }
}
