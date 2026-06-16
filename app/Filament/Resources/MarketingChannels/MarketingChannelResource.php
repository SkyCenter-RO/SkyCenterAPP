<?php

namespace App\Filament\Resources\MarketingChannels;

use App\Filament\Resources\MarketingChannels\Pages\CreateMarketingChannel;
use App\Filament\Resources\MarketingChannels\Pages\EditMarketingChannel;
use App\Filament\Resources\MarketingChannels\Pages\ListMarketingChannels;
use App\Filament\Resources\MarketingChannels\Schemas\MarketingChannelForm;
use App\Filament\Resources\MarketingChannels\Tables\MarketingChannelsTable;
use App\Models\MarketingChannel;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MarketingChannelResource extends Resource
{
    protected static ?string $model = MarketingChannel::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Marketing';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'Canale';

    protected static ?string $modelLabel = 'canal';

    protected static ?string $pluralModelLabel = 'canale';

    protected static ?string $slug = 'marketing-canale';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return MarketingChannelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketingChannelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingChannels::route('/'),
            'create' => CreateMarketingChannel::route('/create'),
            'edit' => EditMarketingChannel::route('/{record}/edit'),
        ];
    }
}
