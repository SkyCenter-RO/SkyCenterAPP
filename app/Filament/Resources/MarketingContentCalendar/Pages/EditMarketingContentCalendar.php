<?php

namespace App\Filament\Resources\MarketingContentCalendar\Pages;

use App\Filament\Resources\MarketingContentCalendar\MarketingContentCalendarResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMarketingContentCalendar extends EditRecord
{
    protected static string $resource = MarketingContentCalendarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
