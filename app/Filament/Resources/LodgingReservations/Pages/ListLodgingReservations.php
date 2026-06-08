<?php

namespace App\Filament\Resources\LodgingReservations\Pages;

use App\Filament\Resources\LodgingReservations\LodgingReservationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLodgingReservations extends ListRecords
{
    protected static string $resource = LodgingReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
