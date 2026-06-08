<?php

namespace App\Filament\Resources\ParkingLots;

use App\Filament\Resources\ParkingLots\Pages\CreateParkingLot;
use App\Filament\Resources\ParkingLots\Pages\EditParkingLot;
use App\Filament\Resources\ParkingLots\Pages\ListParkingLots;
use App\Filament\Resources\ParkingLots\Schemas\ParkingLotForm;
use App\Filament\Resources\ParkingLots\Tables\ParkingLotsTable;
use App\Filament\Resources\ParkingLots\RelationManagers;
use App\Models\ParkingLot;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ParkingLotResource extends Resource
{
    protected static ?string $model = ParkingLot::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Parcare';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Loturi & zone';

    protected static ?string $modelLabel = 'lot de parcare';

    protected static ?string $pluralModelLabel = 'loturi de parcare';

    protected static ?string $slug = 'parcare-loturi';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return ParkingLotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ParkingLotsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ZonesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParkingLots::route('/'),
            'create' => CreateParkingLot::route('/create'),
            'edit' => EditParkingLot::route('/{record}/edit'),
        ];
    }
}
