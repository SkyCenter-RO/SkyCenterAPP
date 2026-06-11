<?php

namespace App\Filament\Resources\MarketingChannels\Pages;

use App\Filament\Resources\MarketingChannels\MarketingChannelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMarketingChannel extends EditRecord
{
    protected static string $resource = MarketingChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
