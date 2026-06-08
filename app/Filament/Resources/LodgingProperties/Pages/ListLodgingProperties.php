<?php

namespace App\Filament\Resources\LodgingProperties\Pages;

use App\Filament\Resources\LodgingProperties\LodgingPropertyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLodgingProperties extends ListRecords
{
    protected static string $resource = LodgingPropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
