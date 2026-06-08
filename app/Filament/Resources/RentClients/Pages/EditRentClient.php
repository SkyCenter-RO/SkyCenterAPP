<?php

namespace App\Filament\Resources\RentClients\Pages;

use App\Filament\Resources\RentClients\RentClientResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRentClient extends EditRecord
{
    protected static string $resource = RentClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
