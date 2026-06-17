<?php

namespace App\Filament\Resources\AutomationWebhookLogs\Schemas;

use App\Enums\AutomationWebhookLogStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AutomationWebhookLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('endpoint')
                    ->required(),
                TextInput::make('idempotency_key'),
                Select::make('status')
                    ->options(AutomationWebhookLogStatus::class)
                    ->required(),
                TextInput::make('http_status')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('event_type'),
                TextInput::make('service'),
                TextInput::make('external_id'),
                TextInput::make('payload'),
                TextInput::make('response_body'),
                TextInput::make('error_message'),
                DateTimePicker::make('received_at')
                    ->required(),
                DateTimePicker::make('processed_at'),
            ]);
    }
}
