<?php

namespace App\Filament\Resources\AutomationWebhookLogs\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('event_type')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('event_type')
            ->columns([
                TextColumn::make('event_type')
                    ->searchable(),
            ])
            ->filters([
                //
            ]);
    }
}
