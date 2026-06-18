<?php

namespace App\Filament\Resources\Payments\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChangeAuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'changeAudits';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('action')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                TextColumn::make('action')
                    ->searchable(),
            ])
            ->filters([
                //
            ]);
    }
}
