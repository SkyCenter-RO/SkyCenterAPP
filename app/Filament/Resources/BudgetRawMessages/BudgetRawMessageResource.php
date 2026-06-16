<?php

namespace App\Filament\Resources\BudgetRawMessages;

use App\Filament\Resources\BudgetRawMessages\Pages\CreateBudgetRawMessage;
use App\Filament\Resources\BudgetRawMessages\Pages\EditBudgetRawMessage;
use App\Filament\Resources\BudgetRawMessages\Pages\ListBudgetRawMessages;
use App\Filament\Resources\BudgetRawMessages\Schemas\BudgetRawMessageForm;
use App\Filament\Resources\BudgetRawMessages\Tables\BudgetRawMessagesTable;
use App\Models\BudgetRawMessage;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BudgetRawMessageResource extends Resource
{
    protected static ?string $model = BudgetRawMessage::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Buget';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Mesaje brute';

    protected static ?string $modelLabel = 'mesaj brut';

    protected static ?string $pluralModelLabel = 'mesaje brute';

    protected static ?string $slug = 'buget-mesaje';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return BudgetRawMessageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BudgetRawMessagesTable::configure($table);
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
            'index' => ListBudgetRawMessages::route('/'),
            'create' => CreateBudgetRawMessage::route('/create'),
            'edit' => EditBudgetRawMessage::route('/{record}/edit'),
        ];
    }
}
