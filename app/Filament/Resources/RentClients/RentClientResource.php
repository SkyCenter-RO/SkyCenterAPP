<?php

namespace App\Filament\Resources\RentClients;

use App\Filament\Resources\RentClients\Pages\CreateRentClient;
use App\Filament\Resources\RentClients\Pages\EditRentClient;
use App\Filament\Resources\RentClients\Pages\ListRentClients;
use App\Filament\Resources\RentClients\Schemas\RentClientForm;
use App\Filament\Resources\RentClients\Tables\RentClientsTable;
use App\Models\RentClient;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class RentClientResource extends Resource
{
    protected static ?string $model = RentClient::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Rent-a-car';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Clienți';

    protected static ?string $modelLabel = 'client rent';

    protected static ?string $pluralModelLabel = 'clienți rent';

    protected static ?string $slug = 'rent-clienti';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return RentClientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentClientsTable::configure($table);
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
            'index' => ListRentClients::route('/'),
            'create' => CreateRentClient::route('/create'),
            'edit' => EditRentClient::route('/{record}/edit'),
        ];
    }
}
