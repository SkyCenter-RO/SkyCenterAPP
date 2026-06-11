<?php

namespace App\Filament\Resources\MarketingAdSpendLogs\Pages;

use App\Filament\Resources\MarketingAdSpendLogs\MarketingAdSpendLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMarketingAdSpendLogs extends ListRecords
{
    protected static string $resource = MarketingAdSpendLogResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
