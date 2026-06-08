<?php

namespace App\Filament\Resources\ParkingReservations\Pages;

use App\Filament\Resources\ParkingReservations\ParkingReservationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListParkingReservations extends ListRecords
{
    protected static string $resource = ParkingReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
