<?php

namespace App\Filament\Resources\ParkingCustomers\Pages;

use App\Filament\Resources\ParkingCustomers\ParkingCustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListParkingCustomers extends ListRecords
{
    protected static string $resource = ParkingCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
