<?php

namespace App\Filament\Resources\RentVehicles\Pages;

use App\Filament\Resources\RentVehicles\RentVehicleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRentVehicles extends ListRecords
{
    protected static string $resource = RentVehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
