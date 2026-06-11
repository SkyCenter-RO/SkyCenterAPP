<?php

namespace App\Filament\Resources\MarketingAdSpendLogs\Pages;

use App\Filament\Resources\MarketingAdSpendLogs\MarketingAdSpendLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMarketingAdSpendLog extends EditRecord
{
    protected static string $resource = MarketingAdSpendLogResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
