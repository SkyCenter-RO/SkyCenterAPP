<?php

namespace App\Filament\Resources\ParkingPrices\Pages;

use App\Filament\Resources\ParkingPrices\ParkingPriceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListParkingPrices extends ListRecords
{
    protected static string $resource = ParkingPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
