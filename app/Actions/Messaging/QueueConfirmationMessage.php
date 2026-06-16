<?php

namespace App\Actions\Messaging;

use App\Models\AutomationEvent;
use App\Models\LodgingReservation;
use App\Models\OutboundMessage;
use App\Models\ParkingReservation;

class QueueConfirmationMessage
{
    public function __construct(private RenderMessageTemplate $renderMessageTemplate) {}

    public function handleParking(ParkingReservation $reservation): void
    {
        $customer = $reservation->customer;

        $placeholders = [
            'name' => $customer?->name ?? '',
            'plate' => $reservation->plate ?? '',
            'check_in' => optional($reservation->check_in_at)->format('d.m.Y H:i') ?? '',
            'check_out' => optional($reservation->check_out_at)->format('d.m.Y H:i') ?? '',
        ];

        $this->queue(
            service: 'parking',
            reservationId: $reservation->id,
            externalId: $reservation->external_id,
            placeholders: $placeholders,
            phone: $customer?->normalized_phone,
            email: $customer?->email,
        );
    }

    public function handleLodging(LodgingReservation $reservation): void
    {
        $room = $reservation->room;
        $property = $room?->property;

        $placeholders = [
            'guest_name' => $reservation->guest_name ?? '',
            'property' => $property?->name ?? '',
            'room' => $room?->name ?? '',
            'check_in' => optional($reservation->check_in)->format('d.m.Y') ?? '',
            'check_out' => optional($reservation->check_out)->format('d.m.Y') ?? '',
        ];

        $this->queue(
            service: 'lodging',
            reservationId: $reservation->id,
            externalId: $reservation->external_id,
            placeholders: $placeholders,
            phone: $reservation->normalized_phone,
            email: $reservation->email,
        );
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    private function queue(
        string $service,
        int $reservationId,
        ?string $externalId,
        array $placeholders,
        ?string $phone,
        ?string $email,
    ): void {
        $rendered = $this->renderMessageTemplate->handle($service, 'confirmation', $placeholders);

        if ($rendered === null) {
            AutomationEvent::create([
                'webhook_log_id' => null,
                'event_type' => 'message_template_missing',
                'service' => $service,
                'external_id' => $externalId,
                'occurred_at' => now(),
                'status' => 'skipped',
                'payload' => ['reservation_id' => $reservationId, 'template_key' => 'confirmation'],
            ]);

            return;
        }

        $contact = $rendered['channel'] === 'email' ? $email : $phone;

        if (! $contact) {
            AutomationEvent::create([
                'webhook_log_id' => null,
                'event_type' => 'message_contact_missing',
                'service' => $service,
                'external_id' => $externalId,
                'occurred_at' => now(),
                'status' => 'skipped',
                'payload' => ['reservation_id' => $reservationId, 'channel' => $rendered['channel']],
            ]);

            return;
        }

        OutboundMessage::create([
            'service' => $service,
            'reference_id' => $reservationId,
            'channel' => $rendered['channel'],
            'template_key' => 'confirmation',
            'payload' => ['text' => $rendered['text'], 'contact' => $contact, 'reservation_id' => $reservationId],
            'scheduled_at' => now(),
            'status' => 'pending',
        ]);

        AutomationEvent::create([
            'webhook_log_id' => null,
            'event_type' => 'confirmation_queued',
            'service' => $service,
            'external_id' => $externalId,
            'occurred_at' => now(),
            'status' => 'processed',
            'payload' => ['reservation_id' => $reservationId, 'channel' => $rendered['channel']],
        ]);
    }
}
