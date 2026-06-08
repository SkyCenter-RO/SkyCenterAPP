<?php

namespace App\Filament\Resources\BudgetRawMessages\Pages;

use App\Filament\Resources\BudgetRawMessages\BudgetRawMessageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBudgetRawMessage extends EditRecord
{
    protected static string $resource = BudgetRawMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
