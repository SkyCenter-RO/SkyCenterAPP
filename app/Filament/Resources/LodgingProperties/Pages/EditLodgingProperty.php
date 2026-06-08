<?php

namespace App\Filament\Resources\LodgingProperties\Pages;

use App\Filament\Resources\LodgingProperties\LodgingPropertyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLodgingProperty extends EditRecord
{
    protected static string $resource = LodgingPropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
