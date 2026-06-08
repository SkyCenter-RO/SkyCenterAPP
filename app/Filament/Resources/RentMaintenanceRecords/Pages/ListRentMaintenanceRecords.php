<?php

namespace App\Filament\Resources\RentMaintenanceRecords\Pages;

use App\Filament\Resources\RentMaintenanceRecords\RentMaintenanceRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRentMaintenanceRecords extends ListRecords
{
    protected static string $resource = RentMaintenanceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
