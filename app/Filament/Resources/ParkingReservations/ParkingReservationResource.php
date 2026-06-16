<?php

namespace App\Filament\Resources\ParkingReservations;

use App\Filament\Resources\ParkingReservations\Pages\CreateParkingReservation;
use App\Filament\Resources\ParkingReservations\Pages\EditParkingReservation;
use App\Filament\Resources\ParkingReservations\Pages\ListParkingReservations;
use App\Filament\Resources\ParkingReservations\Schemas\ParkingReservationForm;
use App\Filament\Resources\ParkingReservations\Tables\ParkingReservationsTable;
use App\Models\ParkingReservation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ParkingReservationResource extends Resource
{
    protected static ?string $model = ParkingReservation::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Parcare';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Rezervări';

    protected static ?string $modelLabel = 'rezervare parcare';

    protected static ?string $pluralModelLabel = 'rezervări parcare';

    protected static ?string $slug = 'parcare-rezervari';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ParkingReservationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ParkingReservationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ImagesRelationManager::class,
            RelationManagers\StatusAuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParkingReservations::route('/'),
            'create' => CreateParkingReservation::route('/create'),
            'edit' => EditParkingReservation::route('/{record}/edit'),
        ];
    }
}
