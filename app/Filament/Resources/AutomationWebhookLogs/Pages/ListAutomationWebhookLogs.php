<?php

namespace App\Filament\Resources\AutomationWebhookLogs\Pages;

use App\Filament\Resources\AutomationWebhookLogs\AutomationWebhookLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAutomationWebhookLogs extends ListRecords
{
    protected static string $resource = AutomationWebhookLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
