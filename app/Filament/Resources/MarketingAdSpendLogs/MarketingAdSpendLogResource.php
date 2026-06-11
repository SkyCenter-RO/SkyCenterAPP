<?php

namespace App\Filament\Resources\MarketingAdSpendLogs;

use App\Filament\Resources\MarketingAdSpendLogs\Pages\CreateMarketingAdSpendLog;
use App\Filament\Resources\MarketingAdSpendLogs\Pages\EditMarketingAdSpendLog;
use App\Filament\Resources\MarketingAdSpendLogs\Pages\ListMarketingAdSpendLogs;
use App\Filament\Resources\MarketingAdSpendLogs\Schemas\MarketingAdSpendLogForm;
use App\Filament\Resources\MarketingAdSpendLogs\Tables\MarketingAdSpendLogsTable;
use App\Models\MarketingAdSpendLog;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MarketingAdSpendLogResource extends Resource
{
    protected static ?string $model = MarketingAdSpendLog::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Marketing';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Cheltuieli Ads';

    protected static ?string $modelLabel = 'cheltuială ads';

    protected static ?string $pluralModelLabel = 'cheltuieli ads';

    protected static ?string $slug = 'marketing-cheltuieli';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof \App\Models\User && $user->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return MarketingAdSpendLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketingAdSpendLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListMarketingAdSpendLogs::route('/'),
            'create' => CreateMarketingAdSpendLog::route('/create'),
            'edit'   => EditMarketingAdSpendLog::route('/{record}/edit'),
        ];
    }
}
