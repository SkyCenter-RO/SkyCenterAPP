<?php

namespace App\Filament\Resources\MarketingReviews;

use App\Filament\Resources\MarketingReviews\Pages\CreateMarketingReview;
use App\Filament\Resources\MarketingReviews\Pages\EditMarketingReview;
use App\Filament\Resources\MarketingReviews\Pages\ListMarketingReviews;
use App\Filament\Resources\MarketingReviews\Schemas\MarketingReviewForm;
use App\Filament\Resources\MarketingReviews\Tables\MarketingReviewsTable;
use App\Models\MarketingReview;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MarketingReviewResource extends Resource
{
    protected static ?string $model = MarketingReview::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Marketing';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Recenzii';

    protected static ?string $modelLabel = 'recenzie';

    protected static ?string $pluralModelLabel = 'recenzii';

    protected static ?string $slug = 'marketing-recenzii';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return MarketingReviewForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketingReviewsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingReviews::route('/'),
            'create' => CreateMarketingReview::route('/create'),
            'edit' => EditMarketingReview::route('/{record}/edit'),
        ];
    }
}
