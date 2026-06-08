<?php

namespace App\Filament\Resources\BudgetCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BudgetCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('service')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('kind')
                    ->required(),
                TextInput::make('frequency')
                    ->required(),
                TextInput::make('default_amount')
                    ->numeric(),
                TextInput::make('currency')
                    ->required()
                    ->default('RON'),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('metadata'),
            ]);
    }
}
