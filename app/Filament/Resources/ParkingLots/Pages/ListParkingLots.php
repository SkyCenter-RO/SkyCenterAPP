<?php

namespace App\Filament\Resources\ParkingLots\Pages;

use App\Filament\Resources\ParkingLots\ParkingLotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListParkingLots extends ListRecords
{
    protected static string $resource = ParkingLotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
