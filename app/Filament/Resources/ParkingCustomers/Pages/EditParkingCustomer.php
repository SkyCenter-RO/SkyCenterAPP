<?php

namespace App\Filament\Resources\ParkingCustomers\Pages;

use App\Filament\Resources\ParkingCustomers\ParkingCustomerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditParkingCustomer extends EditRecord
{
    protected static string $resource = ParkingCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
