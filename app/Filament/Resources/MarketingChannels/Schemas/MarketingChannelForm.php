<?php

namespace App\Filament\Resources\MarketingChannels\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MarketingChannelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(128)
                ->label('Nume canal'),
            Select::make('channel_type')
                ->required()
                ->options([
                    'ads' => 'Ads / Paid',
                    'seo' => 'SEO / Organic Search',
                    'social' => 'Social Media',
                    'listing' => 'Listing / Directory',
                    'affiliate' => 'Affiliate',
                    'email' => 'Email Marketing',
                ])
                ->label('Tip canal'),
            Select::make('status')
                ->required()
                ->default('setup_needed')
                ->options([
                    'active' => 'Activ',
                    'setup_needed' => 'Necesită configurare',
                    'paused' => 'Pauzat',
                    'monitoring' => 'Monitorizare',
                    'blocked' => 'Blocat',
                ])
                ->label('Status'),
            TextInput::make('url')
                ->url()
                ->maxLength(2048)
                ->label('URL'),
            TextInput::make('account_id')
                ->maxLength(255)
                ->label('ID Cont / User'),
            TextInput::make('monthly_budget_eur')
                ->numeric()
                ->prefix('€')
                ->label('Buget lunar (EUR)'),
            DatePicker::make('last_reviewed_at')
                ->label('Ultima revizuire'),
            Textarea::make('notes')
                ->columnSpanFull()
                ->label('Note / Detalii'),
        ]);
    }
}
