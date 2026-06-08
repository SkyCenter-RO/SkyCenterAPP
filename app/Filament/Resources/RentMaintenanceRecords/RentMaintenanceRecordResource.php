<?php

namespace App\Filament\Resources\RentMaintenanceRecords;

use App\Filament\Resources\RentMaintenanceRecords\Pages\CreateRentMaintenanceRecord;
use App\Filament\Resources\RentMaintenanceRecords\Pages\EditRentMaintenanceRecord;
use App\Filament\Resources\RentMaintenanceRecords\Pages\ListRentMaintenanceRecords;
use App\Filament\Resources\RentMaintenanceRecords\Schemas\RentMaintenanceRecordForm;
use App\Filament\Resources\RentMaintenanceRecords\Tables\RentMaintenanceRecordsTable;
use App\Models\RentMaintenanceRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class RentMaintenanceRecordResource extends Resource
{
    protected static ?string $model = RentMaintenanceRecord::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Rent-a-car';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Mentenanță';

    protected static ?string $modelLabel = 'înregistrare mentenanță';

    protected static ?string $pluralModelLabel = 'mentenanță';

    protected static ?string $slug = 'rent-mentenanta';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return RentMaintenanceRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentMaintenanceRecordsTable::configure($table);
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
            'index' => ListRentMaintenanceRecords::route('/'),
            'create' => CreateRentMaintenanceRecord::route('/create'),
            'edit' => EditRentMaintenanceRecord::route('/{record}/edit'),
        ];
    }
}
