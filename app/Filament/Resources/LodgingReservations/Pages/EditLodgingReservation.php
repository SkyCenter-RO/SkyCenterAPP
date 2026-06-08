<?php

namespace App\Filament\Resources\LodgingReservations\Pages;

use App\Filament\Resources\LodgingReservations\LodgingReservationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLodgingReservation extends EditRecord
{
    protected static string $resource = LodgingReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
