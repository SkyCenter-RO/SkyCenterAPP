<?php

namespace App\Filament\Resources\AutomationWebhookLogs;

use App\Filament\Resources\AutomationWebhookLogs\Pages\CreateAutomationWebhookLog;
use App\Filament\Resources\AutomationWebhookLogs\Pages\EditAutomationWebhookLog;
use App\Filament\Resources\AutomationWebhookLogs\Pages\ListAutomationWebhookLogs;
use App\Filament\Resources\AutomationWebhookLogs\Schemas\AutomationWebhookLogForm;
use App\Filament\Resources\AutomationWebhookLogs\Tables\AutomationWebhookLogsTable;
use App\Models\AutomationWebhookLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AutomationWebhookLogResource extends Resource
{
    protected static ?string $model = AutomationWebhookLog::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Sistem';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'Jurnal automatizări';

    protected static ?string $modelLabel = 'intrare jurnal';

    protected static ?string $pluralModelLabel = 'jurnal automatizări';

    protected static ?string $slug = 'sistem-automatizari';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return AutomationWebhookLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AutomationWebhookLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutomationWebhookLogs::route('/'),
            'create' => CreateAutomationWebhookLog::route('/create'),
            'edit' => EditAutomationWebhookLog::route('/{record}/edit'),
        ];
    }
}
