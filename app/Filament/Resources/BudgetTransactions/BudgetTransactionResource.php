<?php

namespace App\Filament\Resources\BudgetTransactions;

use App\Filament\Resources\BudgetTransactions\Pages\CreateBudgetTransaction;
use App\Filament\Resources\BudgetTransactions\Pages\EditBudgetTransaction;
use App\Filament\Resources\BudgetTransactions\Pages\ListBudgetTransactions;
use App\Filament\Resources\BudgetTransactions\Schemas\BudgetTransactionForm;
use App\Filament\Resources\BudgetTransactions\Tables\BudgetTransactionsTable;
use App\Models\BudgetTransaction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BudgetTransactionResource extends Resource
{
    protected static ?string $model = BudgetTransaction::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Buget';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Tranzacții';

    protected static ?string $modelLabel = 'tranzacție';

    protected static ?string $pluralModelLabel = 'tranzacții';

    protected static ?string $slug = 'buget-tranzactii';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof \App\Models\User && $user->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return BudgetTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BudgetTransactionsTable::configure($table);
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
            'index' => ListBudgetTransactions::route('/'),
            'create' => CreateBudgetTransaction::route('/create'),
            'edit' => EditBudgetTransaction::route('/{record}/edit'),
        ];
    }
}
