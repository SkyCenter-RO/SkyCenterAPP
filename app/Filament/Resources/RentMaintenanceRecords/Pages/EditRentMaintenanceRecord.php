<?php

namespace App\Filament\Resources\RentMaintenanceRecords\Pages;

use App\Filament\Resources\RentMaintenanceRecords\RentMaintenanceRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRentMaintenanceRecord extends EditRecord
{
    protected static string $resource = RentMaintenanceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
