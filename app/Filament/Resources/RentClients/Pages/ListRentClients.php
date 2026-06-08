<?php

namespace App\Filament\Resources\RentClients\Pages;

use App\Filament\Resources\RentClients\RentClientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRentClients extends ListRecords
{
    protected static string $resource = RentClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
