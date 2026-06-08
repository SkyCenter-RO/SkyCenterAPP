<?php

namespace App\Filament\Resources\ParkingReservations\Pages;

use App\Filament\Resources\ParkingReservations\ParkingReservationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditParkingReservation extends EditRecord
{
    protected static string $resource = ParkingReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
