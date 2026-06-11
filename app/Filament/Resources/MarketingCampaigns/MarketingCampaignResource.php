<?php

namespace App\Filament\Resources\MarketingCampaigns;

use App\Filament\Resources\MarketingCampaigns\Pages\CreateMarketingCampaign;
use App\Filament\Resources\MarketingCampaigns\Pages\EditMarketingCampaign;
use App\Filament\Resources\MarketingCampaigns\Pages\ListMarketingCampaigns;
use App\Filament\Resources\MarketingCampaigns\Schemas\MarketingCampaignForm;
use App\Filament\Resources\MarketingCampaigns\Tables\MarketingCampaignsTable;
use App\Models\MarketingCampaign;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MarketingCampaignResource extends Resource
{
    protected static ?string $model = MarketingCampaign::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Marketing';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Campanii';

    protected static ?string $modelLabel = 'campanie';

    protected static ?string $pluralModelLabel = 'campanii';

    protected static ?string $slug = 'marketing-campanii';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof \App\Models\User && $user->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return MarketingCampaignForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketingCampaignsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListMarketingCampaigns::route('/'),
            'create' => CreateMarketingCampaign::route('/create'),
            'edit'   => EditMarketingCampaign::route('/{record}/edit'),
        ];
    }
}
