<?php

namespace App\Filament\Resources\Salaries\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SalaryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name'),
                TextInput::make('employee_name'),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('currency')
                    ->required()
                    ->default('RON'),
                DatePicker::make('period_month')
                    ->required(),
                DateTimePicker::make('paid_at'),
                Select::make('status')
                    ->options(\App\Enums\SalaryStatus::class)
                    ->required()
                    ->default(\App\Enums\SalaryStatus::PENDING),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
