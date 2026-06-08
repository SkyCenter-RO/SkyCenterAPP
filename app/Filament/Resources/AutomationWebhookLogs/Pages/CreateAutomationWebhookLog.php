<?php

namespace App\Filament\Resources\AutomationWebhookLogs\Pages;

use App\Filament\Resources\AutomationWebhookLogs\AutomationWebhookLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAutomationWebhookLog extends CreateRecord
{
    protected static string $resource = AutomationWebhookLogResource::class;
}
