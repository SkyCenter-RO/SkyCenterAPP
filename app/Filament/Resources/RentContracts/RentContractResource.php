<?php

namespace App\Filament\Resources\RentContracts;

use App\Filament\Resources\RentContracts\Pages\CreateRentContract;
use App\Filament\Resources\RentContracts\Pages\EditRentContract;
use App\Filament\Resources\RentContracts\Pages\ListRentContracts;
use App\Filament\Resources\RentContracts\Schemas\RentContractForm;
use App\Filament\Resources\RentContracts\Tables\RentContractsTable;
use App\Models\RentContract;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class RentContractResource extends Resource
{
    protected static ?string $model = RentContract::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Rent-a-car';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Contracte';

    protected static ?string $modelLabel = 'contract';

    protected static ?string $pluralModelLabel = 'contracte';

    protected static ?string $slug = 'rent-contracte';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return RentContractForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentContractsTable::configure($table);
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
            'index' => ListRentContracts::route('/'),
            'create' => CreateRentContract::route('/create'),
            'edit' => EditRentContract::route('/{record}/edit'),
        ];
    }
}
