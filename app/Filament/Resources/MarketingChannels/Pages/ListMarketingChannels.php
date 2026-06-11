<?php

namespace App\Filament\Resources\MarketingChannels\Pages;

use App\Filament\Resources\MarketingChannels\MarketingChannelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMarketingChannels extends ListRecords
{
    protected static string $resource = MarketingChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
