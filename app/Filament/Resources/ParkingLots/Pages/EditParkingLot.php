<?php

namespace App\Filament\Resources\ParkingLots\Pages;

use App\Filament\Resources\ParkingLots\ParkingLotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditParkingLot extends EditRecord
{
    protected static string $resource = ParkingLotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
