<?php

namespace App\Filament\Resources\BudgetCategories\Pages;

use App\Filament\Resources\BudgetCategories\BudgetCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBudgetCategory extends EditRecord
{
    protected static string $resource = BudgetCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
