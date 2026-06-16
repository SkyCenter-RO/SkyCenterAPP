<?php

namespace App\Filament\Resources\MarketingReviews\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MarketingReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('platform')
                ->required()
                ->options([
                    'google' => 'Google',
                    'booking' => 'Booking.com',
                    'facebook' => 'Facebook',
                    'tripadvisor' => 'TripAdvisor',
                    'airbnb' => 'Airbnb',
                ]),
            Select::make('vertical')
                ->options([
                    'hotel' => 'Hotel',
                    'parcare' => 'Parcare',
                    'rent' => 'Rent-a-car',
                    'all' => 'Toate',
                ]),
            TextInput::make('score')
                ->required()
                ->numeric()
                ->minValue(1)
                ->maxValue(10)
                ->step(0.01)
                ->label('Scor'),
            TextInput::make('review_count')
                ->numeric()
                ->minValue(0)
                ->label('Număr recenzii'),
            DatePicker::make('recorded_on')
                ->required()
                ->label('Data înregistrării'),
            Textarea::make('notes')
                ->columnSpanFull()
                ->label('Note'),
        ]);
    }
}
