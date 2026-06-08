<?php

namespace App\Filament\Resources\OutboundMessages;

use App\Filament\Resources\OutboundMessages\Pages\CreateOutboundMessage;
use App\Filament\Resources\OutboundMessages\Pages\EditOutboundMessage;
use App\Filament\Resources\OutboundMessages\Pages\ListOutboundMessages;
use App\Filament\Resources\OutboundMessages\Schemas\OutboundMessageForm;
use App\Filament\Resources\OutboundMessages\Tables\OutboundMessagesTable;
use App\Models\OutboundMessage;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class OutboundMessageResource extends Resource
{
    protected static ?string $model = OutboundMessage::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Sistem';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Mesaje trimise';

    protected static ?string $modelLabel = 'mesaj trimis';

    protected static ?string $pluralModelLabel = 'mesaje trimise';

    protected static ?string $slug = 'sistem-mesaje-trimise';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return OutboundMessageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OutboundMessagesTable::configure($table);
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
            'index' => ListOutboundMessages::route('/'),
            'create' => CreateOutboundMessage::route('/create'),
            'edit' => EditOutboundMessage::route('/{record}/edit'),
        ];
    }
}
