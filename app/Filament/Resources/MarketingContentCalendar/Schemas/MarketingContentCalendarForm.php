<?php

namespace App\Filament\Resources\MarketingContentCalendar\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MarketingContentCalendarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->columnSpanFull()
                ->label('Titlu'),
            Select::make('platform')
                ->required()
                ->options([
                    'facebook' => 'Facebook',
                    'instagram' => 'Instagram',
                    'tiktok' => 'TikTok',
                    'all' => 'Toate platformele',
                ])
                ->label('Platformă'),
            Select::make('vertical')
                ->options([
                    'hotel' => 'Hotel',
                    'parcare' => 'Parcare',
                    'rent' => 'Rent-a-car',
                    'all' => 'Toate',
                ])
                ->label('Verticală'),
            Select::make('content_type')
                ->required()
                ->options([
                    'photo' => 'Foto',
                    'reel' => 'Reel',
                    'story' => 'Story',
                    'carousel' => 'Carusel',
                    'text' => 'Text',
                ])
                ->label('Tip conținut'),
            Select::make('language')
                ->required()
                ->default('ro')
                ->options([
                    'ro' => 'Română',
                    'en' => 'Engleză',
                    'it' => 'Italiană',
                    'ru' => 'Rusă',
                ])
                ->label('Limbă'),
            Select::make('status')
                ->required()
                ->default('idea')
                ->options([
                    'idea' => 'Idee',
                    'in_progress' => 'În lucru',
                    'ready' => 'Pregătită',
                    'scheduled' => 'Programată',
                    'published' => 'Publicată',
                    'cancelled' => 'Anulată',
                ])
                ->label('Status'),
            DatePicker::make('scheduled_at')
                ->label('Data programată'),
            DatePicker::make('published_at')
                ->label('Data publicării'),
            Textarea::make('copy_text')
                ->columnSpanFull()
                ->label('Text postare (Copy)'),
            Textarea::make('notes')
                ->columnSpanFull()
                ->label('Note'),
        ]);
    }
}
