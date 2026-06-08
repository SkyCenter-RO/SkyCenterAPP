<?php

namespace App\Filament\Resources\LodgingReservations;

use App\Filament\Resources\LodgingReservations\Pages\CreateLodgingReservation;
use App\Filament\Resources\LodgingReservations\Pages\EditLodgingReservation;
use App\Filament\Resources\LodgingReservations\Pages\ListLodgingReservations;
use App\Filament\Resources\LodgingReservations\Schemas\LodgingReservationForm;
use App\Filament\Resources\LodgingReservations\Tables\LodgingReservationsTable;
use App\Models\LodgingReservation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class LodgingReservationResource extends Resource
{
    protected static ?string $model = LodgingReservation::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Cazare';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Rezervări';

    protected static ?string $modelLabel = 'rezervare cazare';

    protected static ?string $pluralModelLabel = 'rezervări cazare';

    protected static ?string $slug = 'cazare-rezervari';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return LodgingReservationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LodgingReservationsTable::configure($table);
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
            'index' => ListLodgingReservations::route('/'),
            'create' => CreateLodgingReservation::route('/create'),
            'edit' => EditLodgingReservation::route('/{record}/edit'),
        ];
    }
}
