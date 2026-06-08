<?php

namespace App\Filament\Resources\OutboundMessages\Pages;

use App\Filament\Resources\OutboundMessages\OutboundMessageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOutboundMessage extends CreateRecord
{
    protected static string $resource = OutboundMessageResource::class;
}
