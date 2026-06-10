<?php

namespace App\Actions\Automation;

use App\Actions\Messaging\RenderMessageTemplate;
use App\Models\AutomationEvent;
use App\Models\LodgingReservation;
use App\Models\OutboundMessage;
use App\Models\ParkingReservation;

class DispatchReviewRequests
{
    public function __construct(private RenderMessageTemplate $renderMessageTemplate)
    {
    }

    /**
     * @return array{parking_queued: int, lodging_queued: int, skipped: int}
     */
    public function handle(): array
    {
        $parkingQueued = 0;
        $lodgingQueued = 0;
        $skipped = 0;

        $parkingReservations = ParkingReservation::query()
            ->where('status', 'departed')
            ->where('check_out_at', '<=', now()->subHours(24))
            ->where('review_request_sent', false)
            ->get();

        foreach ($parkingReservations as $reservation) {
            $this->queueParkingReviewRequest($reservation) ? $parkingQueued++ : $skipped++;
        }

        $lodgingReservations = LodgingReservation::query()
            ->where('status', 'checked_out')
            ->where('check_out', '<=', now()->subDay()->toDateString())
            ->where('review_request_sent', false)
            ->get();

        foreach ($lodgingReservations as $reservation) {
            $this->queueLodgingReviewRequest($reservation) ? $lodgingQueued++ : $skipped++;
        }

        return [
            'parking_queued' => $parkingQueued,
            'lodging_queued' => $lodgingQueued,
            'skipped' => $skipped,
        ];
    }

    private function queueParkingReviewRequest(ParkingReservation $reservation): bool
    {
        $customer = $reservation->customer;

        $rendered = $this->renderMessageTemplate->handle('parking', 'review_request', [
            'name' => $customer?->name ?? '',
        ]);

        return $this->queue($reservation, 'parking', $rendered, $customer?->normalized_phone, $customer?->email);
    }

    private function queueLodgingReviewRequest(LodgingReservation $reservation): bool
    {
        $property = $reservation->room?->property;

        $rendered = $this->renderMessageTemplate->handle('lodging', 'review_request', [
            'guest_name' => $reservation->guest_name ?? '',
            'property' => $property?->name ?? '',
        ]);

        return $this->queue($reservation, 'lodging', $rendered, $reservation->normalized_phone, $reservation->email);
    }

    /**
     * @param  array{channel: string, text: string}|null  $rendered
     */
    private function queue(
        ParkingReservation|LodgingReservation $reservation,
        string $service,
        ?array $rendered,
        ?string $phone,
        ?string $email,
    ): bool {
        if ($rendered === null) {
            AutomationEvent::create([
                'webhook_log_id' => null,
                'event_type' => 'message_template_missing',
                'service' => $service,
                'external_id' => $reservation->external_id,
                'occurred_at' => now(),
                'status' => 'skipped',
                'payload' => ['reservation_id' => $reservation->id, 'template_key' => 'review_request'],
            ]);

            return false;
        }

        $contact = $rendered['channel'] === 'email' ? $email : $phone;

        if (! $contact) {
            AutomationEvent::create([
                'webhook_log_id' => null,
                'event_type' => 'message_contact_missing',
                'service' => $service,
                'external_id' => $reservation->external_id,
                'occurred_at' => now(),
                'status' => 'skipped',
                'payload' => ['reservation_id' => $reservation->id, 'channel' => $rendered['channel']],
            ]);

            return false;
        }

        OutboundMessage::create([
            'service' => $service,
            'reference_id' => $reservation->id,
            'channel' => $rendered['channel'],
            'template_key' => 'review_request',
            'payload' => ['text' => $rendered['text'], 'contact' => $contact, 'reservation_id' => $reservation->id],
            'scheduled_at' => now(),
            'status' => 'pending',
        ]);

        $reservation->update(['review_request_sent' => true]);

        AutomationEvent::create([
            'webhook_log_id' => null,
            'event_type' => 'review_request_queued',
            'service' => $service,
            'external_id' => $reservation->external_id,
            'occurred_at' => now(),
            'status' => 'processed',
            'payload' => ['reservation_id' => $reservation->id, 'channel' => $rendered['channel']],
        ]);

        return true;
    }
}
