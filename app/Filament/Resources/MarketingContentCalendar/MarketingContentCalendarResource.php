<?php

namespace App\Filament\Resources\MarketingContentCalendar;

use App\Filament\Resources\MarketingContentCalendar\Pages\CreateMarketingContentCalendar;
use App\Filament\Resources\MarketingContentCalendar\Pages\EditMarketingContentCalendar;
use App\Filament\Resources\MarketingContentCalendar\Pages\ListMarketingContentCalendars;
use App\Filament\Resources\MarketingContentCalendar\Schemas\MarketingContentCalendarForm;
use App\Filament\Resources\MarketingContentCalendar\Tables\MarketingContentCalendarTable;
use App\Models\MarketingContentCalendar;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MarketingContentCalendarResource extends Resource
{
    protected static ?string $model = MarketingContentCalendar::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Marketing';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Calendar Conținut';

    protected static ?string $modelLabel = 'postare';

    protected static ?string $pluralModelLabel = 'postări';

    protected static ?string $slug = 'marketing-calendar';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return MarketingContentCalendarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketingContentCalendarTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingContentCalendars::route('/'),
            'create' => CreateMarketingContentCalendar::route('/create'),
            'edit' => EditMarketingContentCalendar::route('/{record}/edit'),
        ];
    }
}
