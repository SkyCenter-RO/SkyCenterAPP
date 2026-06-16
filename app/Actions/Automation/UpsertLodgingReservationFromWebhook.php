<?php

namespace App\Actions\Automation;

use App\Models\AutomationEvent;
use App\Models\AutomationWebhookLog;
use App\Models\LodgingReservation;
use App\Support\PhoneNumber;

class UpsertLodgingReservationFromWebhook
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

        $source = $payload['source'] ?? null;
        $externalId = $payload['external_id'] ?? null;
        $checkIn = $payload['check_in'] ?? null;
        $checkOut = $payload['check_out'] ?? null;

        if (! $source || ! $externalId || ! $checkIn || ! $checkOut) {
            return $this->error('Missing required fields: source, external_id, check_in, check_out');
        }

        $reservation = \DB::transaction(function () use ($source, $externalId, $payload, $log) {
            $existing = LodgingReservation::query()
                ->where('source', $source)
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();

            $reservation = $existing ?? new LodgingReservation;
            $reservation->fill([
                'source' => $source,
                'external_id' => $externalId,
                'guest_name' => $payload['guest_name'] ?? $reservation->guest_name,
                'phone' => $payload['phone'] ?? $reservation->phone,
                'normalized_phone' => PhoneNumber::normalize($payload['phone'] ?? null) ?? $reservation->normalized_phone,
                'email' => $payload['email'] ?? $reservation->email,
                'status' => $existing?->status ?? 'pending',
                'check_in' => $payload['check_in'] ?? null,
                'check_out' => $payload['check_out'] ?? null,
                'nights' => $payload['nights'] ?? $reservation->nights,
                'price' => $payload['price'] ?? $reservation->price,
                'currency' => $payload['currency'] ?? $reservation->currency ?? 'RON',
            ]);
            $reservation->save();

            AutomationEvent::create([
                'webhook_log_id' => $log->id,
                'event_type' => $existing ? 'reservation_updated' : 'reservation_created',
                'service' => 'lodging',
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
