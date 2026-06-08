<?php

namespace App\Filament\Resources\RentVehicles\Pages;

use App\Filament\Resources\RentVehicles\RentVehicleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRentVehicle extends EditRecord
{
    protected static string $resource = RentVehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
