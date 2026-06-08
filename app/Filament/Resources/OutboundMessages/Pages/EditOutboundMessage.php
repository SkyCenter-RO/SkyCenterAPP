<?php

namespace App\Filament\Resources\OutboundMessages\Pages;

use App\Filament\Resources\OutboundMessages\OutboundMessageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOutboundMessage extends EditRecord
{
    protected static string $resource = OutboundMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
