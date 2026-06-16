<?php

namespace App\Filament\Resources\LodgingProperties;

use App\Filament\Resources\LodgingProperties\Pages\CreateLodgingProperty;
use App\Filament\Resources\LodgingProperties\Pages\EditLodgingProperty;
use App\Filament\Resources\LodgingProperties\Pages\ListLodgingProperties;
use App\Filament\Resources\LodgingProperties\Schemas\LodgingPropertyForm;
use App\Filament\Resources\LodgingProperties\Tables\LodgingPropertiesTable;
use App\Models\LodgingProperty;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class LodgingPropertyResource extends Resource
{
    protected static ?string $model = LodgingProperty::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Cazare';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Proprietăți';

    protected static ?string $modelLabel = 'proprietate';

    protected static ?string $pluralModelLabel = 'proprietăți';

    protected static ?string $slug = 'cazare-proprietati';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return LodgingPropertyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LodgingPropertiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SyncLinksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLodgingProperties::route('/'),
            'create' => CreateLodgingProperty::route('/create'),
            'edit' => EditLodgingProperty::route('/{record}/edit'),
        ];
    }
}
