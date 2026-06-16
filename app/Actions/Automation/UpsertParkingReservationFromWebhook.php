<?php

namespace App\Actions\Automation;

use App\Models\AutomationEvent;
use App\Models\AutomationWebhookLog;
use App\Models\ParkingCustomer;
use App\Models\ParkingReservation;
use App\Support\PhoneNumber;

class UpsertParkingReservationFromWebhook
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: string, http_status: int, response: array<string, mixed>, error?: string}
     */
    public function handle(array $payload, AutomationWebhookLog $log): array
    {
        if (($payload['event_type'] ?? null) === 'unparsed') {
            return $this->error('Email could not be parsed');
        }

        $externalId = $payload['external_id'] ?? null;
        $checkInAt = $payload['check_in_at'] ?? null;
        $checkOutAt = $payload['check_out_at'] ?? null;

        if (! $externalId || ! $checkInAt || ! $checkOutAt) {
            return $this->error('Missing required fields: external_id, check_in_at, check_out_at');
        }

        $reservation = \DB::transaction(function () use ($payload, $externalId, $checkInAt, $checkOutAt, $log) {
            $customer = $this->upsertCustomer($payload);

            $existing = ParkingReservation::query()
                ->where('source', 'parcare_form')
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();

            $reservation = $existing ?? new ParkingReservation;
            $reservation->fill([
                'source' => 'parcare_form',
                'external_id' => $externalId,
                'customer_id' => $customer?->id ?? $reservation->customer_id,
                'lot_id' => $payload['lot_id'] ?? config('skycenter.default_parking_lot_id') ?? $reservation->lot_id,
                'status' => $existing?->status ?? 'pending_approval',
                'plate' => $payload['plate'] ?? $reservation->plate,
                'vehicle_type' => $payload['vehicle_type'] ?? $reservation->vehicle_type,
                'check_in_at' => $checkInAt,
                'check_out_at' => $checkOutAt,
                'adults' => $payload['adults'] ?? $reservation->adults,
                'children' => $payload['children'] ?? $reservation->children,
                'quoted_price' => $payload['quoted_price'] ?? $reservation->quoted_price,
                'currency' => $payload['currency'] ?? $reservation->currency ?? 'RON',
            ]);
            $reservation->save();

            AutomationEvent::create([
                'webhook_log_id' => $log->id,
                'event_type' => $existing ? 'reservation_updated' : 'reservation_created',
                'service' => 'parking',
                'external_id' => $externalId,
                'occurred_at' => now(),
                'status' => 'processed',
                'payload' => $payload,
            ]);

            return $reservation;
        });

        return [
            'status' => 'processed',
            'http_status' => 200,
            'response' => ['id' => $reservation->id, 'status' => $reservation->status],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertCustomer(array $payload): ?ParkingCustomer
    {
        $normalizedPhone = PhoneNumber::normalize($payload['phone'] ?? null);

        if (! $normalizedPhone) {
            return null;
        }

        $customer = ParkingCustomer::query()->firstOrNew(['normalized_phone' => $normalizedPhone]);
        $customer->fill([
            'source' => 'parcare_form',
            'name' => $payload['name'] ?? $customer->name,
            'phone' => $payload['phone'] ?? $customer->phone,
            'normalized_phone' => $normalizedPhone,
            'email' => $payload['email'] ?? $customer->email,
        ]);
        $customer->save();

        return $customer;
    }

    /**
     * @return array{status: string, http_status: int, response: array<string, mixed>, error: string}
     */
    private function error(string $message): array
    {
        return [
            'status' => 'error',
            'http_status' => 422,
            'error' => $message,
            'response' => ['message' => $message],
        ];
    }
}
