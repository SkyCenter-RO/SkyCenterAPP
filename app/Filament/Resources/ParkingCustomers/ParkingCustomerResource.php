<?php

namespace App\Filament\Resources\ParkingCustomers;

use App\Filament\Resources\ParkingCustomers\Pages\CreateParkingCustomer;
use App\Filament\Resources\ParkingCustomers\Pages\EditParkingCustomer;
use App\Filament\Resources\ParkingCustomers\Pages\ListParkingCustomers;
use App\Filament\Resources\ParkingCustomers\Schemas\ParkingCustomerForm;
use App\Filament\Resources\ParkingCustomers\Tables\ParkingCustomersTable;
use App\Models\ParkingCustomer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ParkingCustomerResource extends Resource
{
    protected static ?string $model = ParkingCustomer::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Parcare';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Clienți';

    protected static ?string $modelLabel = 'client parcare';

    protected static ?string $pluralModelLabel = 'clienți parcare';

    protected static ?string $slug = 'parcare-clienti';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ParkingCustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ParkingCustomersTable::configure($table);
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
            'index' => ListParkingCustomers::route('/'),
            'create' => CreateParkingCustomer::route('/create'),
            'edit' => EditParkingCustomer::route('/{record}/edit'),
        ];
    }
}
