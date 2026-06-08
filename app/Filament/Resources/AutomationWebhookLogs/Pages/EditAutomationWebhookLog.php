<?php

namespace App\Filament\Resources\AutomationWebhookLogs\Pages;

use App\Filament\Resources\AutomationWebhookLogs\AutomationWebhookLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAutomationWebhookLog extends EditRecord
{
    protected static string $resource = AutomationWebhookLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
