<?php

namespace App\Filament\Resources\MarketingContentCalendar\Pages;

use App\Filament\Resources\MarketingContentCalendar\MarketingContentCalendarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMarketingContentCalendars extends ListRecords
{
    protected static string $resource = MarketingContentCalendarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
