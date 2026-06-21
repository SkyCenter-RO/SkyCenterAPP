<?php

namespace App\Filament\Resources\LodgingReservations\Schemas;

use App\Enums\LodgingReservationStatus;
use App\Models\LodgingReservation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LodgingReservationForm
{
    private const SOURCE_OPTIONS = [
        'gmail' => 'Email',
        'booking_com' => 'Booking.com',
        'airbnb' => 'Airbnb',
        'direct' => 'Direct',
    ];

    private const LEGACY_SOURCE_LABELS = [
        'manual' => 'Manual (legacy)',
        'booking' => 'Booking (legacy)',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('source')
                    ->options(fn (?LodgingReservation $record): array => self::sourceOptions($record))
                    ->required()
                    ->default('direct'),
                TextInput::make('external_id'),
                Select::make('room_id')
                    ->relationship('room', 'name'),
                TextInput::make('guest_name'),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('normalized_phone')
                    ->tel(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                Select::make('status')
                    ->options(LodgingReservationStatus::class)
                    ->nullable(),
                DatePicker::make('check_in')
                    ->required(),
                DatePicker::make('check_out')
                    ->required()
                    ->after('check_in')
                    ->rules([
                        fn ($get, $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $checkIn = $get('check_in');
                            $roomId = $get('room_id');
                            if (! $checkIn || ! $roomId || ! $value) {
                                return;
                            }

                            $overlap = LodgingReservation::query()
                                ->where('room_id', $roomId)
                                ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                ->where('status', '!=', 'cancelled')
                                ->where(function ($query) use ($checkIn, $value) {
                                    $query->where('check_in', '<', $value)
                                        ->where('check_out', '>', $checkIn);
                                })
                                ->exists();

                            if ($overlap) {
                                $fail('Această cameră este deja rezervată pentru perioada selectată.');
                            }
                        },
                    ]),
                TextInput::make('nights')
                    ->numeric(),
                TextInput::make('price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('direct_price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('currency')
                    ->required()
                    ->default('RON'),
                DateTimePicker::make('source_created_at'),
                Textarea::make('notes')
                    ->columnSpanFull(),
                TextInput::make('metadata'),
                TextInput::make('created_by_id')
                    ->numeric(),
                TextInput::make('updated_by_id')
                    ->numeric(),
            ]);
    }

    private static function sourceOptions(?LodgingReservation $record): array
    {
        $options = self::SOURCE_OPTIONS;

        if ($record && isset(self::LEGACY_SOURCE_LABELS[$record->source])) {
            $options[$record->source] = self::LEGACY_SOURCE_LABELS[$record->source];
        }

        return $options;
    }
}
