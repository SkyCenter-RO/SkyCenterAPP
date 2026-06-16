<?php

namespace App\Filament\Resources\MessageTemplates\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MessageTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('source')
                    ->required()
                    ->default('manual'),
                TextInput::make('external_id'),
                TextInput::make('template_key')
                    ->required(),
                TextInput::make('service'),
                TextInput::make('channel')
                    ->required(),
                TextInput::make('locale')
                    ->required()
                    ->default('ro'),
                TextInput::make('label'),
                Textarea::make('body')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('metadata'),
            ]);
    }
}
