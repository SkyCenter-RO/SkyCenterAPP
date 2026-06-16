<?php

namespace App\Filament\Resources\Salaries;

use App\Filament\Resources\Salaries\Pages\CreateSalary;
use App\Filament\Resources\Salaries\Pages\EditSalary;
use App\Filament\Resources\Salaries\Pages\ListSalaries;
use App\Filament\Resources\Salaries\Schemas\SalaryForm;
use App\Filament\Resources\Salaries\Tables\SalariesTable;
use App\Models\Salary;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SalaryResource extends Resource
{
    protected static ?string $model = Salary::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Buget';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Salarii';

    protected static ?string $modelLabel = 'salariu';

    protected static ?string $pluralModelLabel = 'salarii';

    protected static ?string $slug = 'buget-salarii';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return SalaryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalariesTable::configure($table);
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
            'index' => ListSalaries::route('/'),
            'create' => CreateSalary::route('/create'),
            'edit' => EditSalary::route('/{record}/edit'),
        ];
    }
}
