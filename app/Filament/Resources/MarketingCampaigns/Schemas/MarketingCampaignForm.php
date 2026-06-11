<?php

namespace App\Filament\Resources\MarketingCampaigns\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MarketingCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
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
                ->required()
                ->options([
                    'parcare' => 'Parcare',
                    'hotel'   => 'Hotel',
                    'rent'    => 'Rent-a-car',
                    'bundle'  => 'Bundle',
                    'general' => 'General',
                ]),
            Select::make('status')
                ->required()
                ->default('active')
                ->options([
                    'active'    => 'Activ',
                    'paused'    => 'Pauzat',
                    'completed' => 'Finalizat',
                    'draft'     => 'Draft',
                ]),
            DatePicker::make('period_month')
                ->required()
                ->label('Luna (prima zi a lunii)'),
            TextInput::make('budget_eur')
                ->numeric()
                ->prefix('€')
                ->label('Buget alocat (EUR)'),
            TextInput::make('spend_eur')
                ->numeric()
                ->prefix('€')
                ->label('Cheltuieli reale (EUR)'),
            TextInput::make('conversions')
                ->numeric()
                ->label('Conversii (apeluri/rezervări)'),
            TextInput::make('cpc_eur')
                ->numeric()
                ->prefix('€')
                ->label('CPC mediu (EUR)'),
            TextInput::make('roas')
                ->numeric()
                ->label('ROAS'),
            Textarea::make('notes')
                ->columnSpanFull(),
        ]);
    }
}
