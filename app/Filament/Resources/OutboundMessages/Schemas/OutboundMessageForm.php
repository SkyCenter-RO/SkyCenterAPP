<?php

namespace App\Filament\Resources\OutboundMessages\Schemas;

use App\Enums\OutboundMessageStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OutboundMessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('service')
                    ->required(),
                TextInput::make('reference_id')
                    ->numeric(),
                TextInput::make('channel')
                    ->required(),
                TextInput::make('template_key'),
                TextInput::make('payload'),
                DateTimePicker::make('scheduled_at')
                    ->required(),
                DateTimePicker::make('sent_at'),
                Select::make('status')
                    ->options(OutboundMessageStatus::class)
                    ->required()
                    ->default(OutboundMessageStatus::PENDING),
            ]);
    }
}
