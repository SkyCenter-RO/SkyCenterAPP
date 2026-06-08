<?php

namespace App\Filament\Resources\BudgetRawMessages\Pages;

use App\Filament\Resources\BudgetRawMessages\BudgetRawMessageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBudgetRawMessages extends ListRecords
{
    protected static string $resource = BudgetRawMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
