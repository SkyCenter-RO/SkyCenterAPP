<?php

namespace App\Filament\Resources\MarketingAdSpendLogs\Schemas;

use App\Models\MarketingCampaign;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MarketingAdSpendLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('campaign_id')
                ->label('Campanie')
                ->options(MarketingCampaign::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->nullable(),
            Select::make('platform')
                ->required()
                ->options([
                    'google'    => 'Google',
                    'facebook'  => 'Facebook',
                    'instagram' => 'Instagram',
                    'tiktok'    => 'TikTok',
                    'bing'      => 'Bing',
                    'other'     => 'Altul',
                ]),
            Select::make('vertical')
                ->nullable()
                ->options([
                    'parcare' => 'Parcare',
                    'hotel'   => 'Hotel',
                    'rent'    => 'Rent-a-car',
                    'bundle'  => 'Bundle',
                    'general' => 'General',
                ]),
            TextInput::make('amount_eur')
                ->required()
                ->numeric()
                ->prefix('€')
                ->label('Sumă (EUR)'),
            DatePicker::make('spent_on')
                ->required()
                ->label('Data cheltuielii')
                ->default(today()),
            Textarea::make('notes')
                ->columnSpanFull(),
        ]);
    }
}
