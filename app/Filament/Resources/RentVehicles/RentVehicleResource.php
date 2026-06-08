<?php

namespace App\Filament\Resources\RentVehicles;

use App\Filament\Resources\RentVehicles\Pages\CreateRentVehicle;
use App\Filament\Resources\RentVehicles\Pages\EditRentVehicle;
use App\Filament\Resources\RentVehicles\Pages\ListRentVehicles;
use App\Filament\Resources\RentVehicles\Schemas\RentVehicleForm;
use App\Filament\Resources\RentVehicles\Tables\RentVehiclesTable;
use App\Filament\Resources\RentVehicles\RelationManagers;
use App\Models\RentVehicle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class RentVehicleResource extends Resource
{
    protected static ?string $model = RentVehicle::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Rent-a-car';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Mașini';

    protected static ?string $modelLabel = 'mașină';

    protected static ?string $pluralModelLabel = 'mașini';

    protected static ?string $slug = 'rent-masini';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return RentVehicleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentVehiclesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ImagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRentVehicles::route('/'),
            'create' => CreateRentVehicle::route('/create'),
            'edit' => EditRentVehicle::route('/{record}/edit'),
        ];
    }
}
