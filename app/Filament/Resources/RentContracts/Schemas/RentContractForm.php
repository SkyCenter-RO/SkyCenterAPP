<?php

namespace App\Filament\Resources\RentContracts\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class RentContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('source')
                    ->required()
                    ->default('manual'),
                TextInput::make('external_id'),
                TextInput::make('contract_code'),
                TextInput::make('rent_vehicle_id')
                    ->numeric(),
                TextInput::make('rent_client_id')
                    ->numeric(),
                TextInput::make('usage_type')
                    ->required(),
                DatePicker::make('start_date')
                    ->required(),
                DatePicker::make('end_date')
                    ->required()
                    ->after('start_date')
                    ->rules([
                        fn ($get, $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $startDate = $get('start_date');
                            $vehicleId = $get('rent_vehicle_id');
                            if (! $startDate || ! $vehicleId || ! $value) {
                                return;
                            }

                            $overlap = \App\Models\RentContract::query()
                                ->where('rent_vehicle_id', $vehicleId)
                                ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                ->where('status', '!=', 'cancelled')
                                ->where(function ($query) use ($startDate, $value) {
                                    $query->where('start_date', '<', $value)
                                          ->where('end_date', '>', $startDate);
                                })
                                ->exists();

                            if ($overlap) {
                                $fail('Acest vehicul este deja închiriat pentru perioada selectată.');
                            }
                        }
                    ]),
                TextInput::make('km_at_handover')
                    ->numeric(),
                TextInput::make('km_at_return')
                    ->numeric(),
                TextInput::make('daily_price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('monthly_price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('warranty_collected')
                    ->numeric(),
                TextInput::make('total_price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('currency')
                    ->required()
                    ->default('RON'),
                TextInput::make('status')
                    ->required()
                    ->default('active'),
                Textarea::make('notes')
                    ->columnSpanFull(),
                TextInput::make('metadata'),
                TextInput::make('created_by_id')
                    ->numeric(),
                TextInput::make('updated_by_id')
                    ->numeric(),
            ]);
    }
}
