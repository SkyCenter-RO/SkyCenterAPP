<?php

namespace App\Filament\Resources\ParkingPrices;

use App\Filament\Resources\ParkingPrices\Pages\CreateParkingPrice;
use App\Filament\Resources\ParkingPrices\Pages\EditParkingPrice;
use App\Filament\Resources\ParkingPrices\Pages\ListParkingPrices;
use App\Filament\Resources\ParkingPrices\Schemas\ParkingPriceForm;
use App\Filament\Resources\ParkingPrices\Tables\ParkingPricesTable;
use App\Models\ParkingPrice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ParkingPriceResource extends Resource
{
    protected static ?string $model = ParkingPrice::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Parcare';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Prețuri';

    protected static ?string $modelLabel = 'preț parcare';

    protected static ?string $pluralModelLabel = 'prețuri parcare';

    protected static ?string $slug = 'parcare-preturi';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return ParkingPriceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ParkingPricesTable::configure($table);
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
            'index' => ListParkingPrices::route('/'),
            'create' => CreateParkingPrice::route('/create'),
            'edit' => EditParkingPrice::route('/{record}/edit'),
        ];
    }
}
