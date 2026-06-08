<?php

namespace App\Filament\Resources\RentContracts\Pages;

use App\Filament\Resources\RentContracts\RentContractResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRentContract extends EditRecord
{
    protected static string $resource = RentContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
