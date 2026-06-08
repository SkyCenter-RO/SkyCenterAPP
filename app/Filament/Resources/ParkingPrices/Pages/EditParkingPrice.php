<?php

namespace App\Filament\Resources\ParkingPrices\Pages;

use App\Filament\Resources\ParkingPrices\ParkingPriceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditParkingPrice extends EditRecord
{
    protected static string $resource = ParkingPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
