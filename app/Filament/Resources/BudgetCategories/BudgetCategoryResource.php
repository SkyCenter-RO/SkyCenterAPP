<?php

namespace App\Filament\Resources\BudgetCategories;

use App\Filament\Resources\BudgetCategories\Pages\CreateBudgetCategory;
use App\Filament\Resources\BudgetCategories\Pages\EditBudgetCategory;
use App\Filament\Resources\BudgetCategories\Pages\ListBudgetCategories;
use App\Filament\Resources\BudgetCategories\Schemas\BudgetCategoryForm;
use App\Filament\Resources\BudgetCategories\Tables\BudgetCategoriesTable;
use App\Models\BudgetCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BudgetCategoryResource extends Resource
{
    protected static ?string $model = BudgetCategory::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Buget';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Categorii';

    protected static ?string $modelLabel = 'categorie buget';

    protected static ?string $pluralModelLabel = 'categorii buget';

    protected static ?string $slug = 'buget-categorii';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return BudgetCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BudgetCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBudgetCategories::route('/'),
            'create' => CreateBudgetCategory::route('/create'),
            'edit' => EditBudgetCategory::route('/{record}/edit'),
        ];
    }
}
