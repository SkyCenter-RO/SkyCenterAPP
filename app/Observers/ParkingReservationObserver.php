<?php

namespace App\Observers;

use App\Actions\Messaging\QueueConfirmationMessage;
use App\Enums\ParkingReservationStatus;
use App\Models\ParkingReservation;
use App\Models\ParkingStatusAudit;

class ParkingReservationObserver
{
    public function __construct(private QueueConfirmationMessage $queueConfirmation) {}

    public function created(ParkingReservation $reservation): void
    {
        ParkingStatusAudit::create([
            'parking_reservation_id' => $reservation->id,
            'user_id' => auth()->id() ?? $reservation->updated_by_id ?? $reservation->created_by_id,
            'from_status' => null,
            'to_status' => $reservation->status->value,
            'changed_at' => now(),
        ]);

        if ($reservation->status === ParkingReservationStatus::BOOKED) {
            $this->queueConfirmation->handleParking($reservation);
        }
    }

    public function updated(ParkingReservation $reservation): void
    {
        if ($reservation->wasChanged('status')) {
            ParkingStatusAudit::create([
                'parking_reservation_id' => $reservation->id,
                'user_id' => auth()->id() ?? $reservation->updated_by_id,
                'from_status' => $reservation->getOriginal('status'),
                'to_status' => $reservation->status->value,
                'changed_at' => now(),
            ]);
        }

        if ($reservation->wasChanged('status')
            && $reservation->status === ParkingReservationStatus::BOOKED
            && $reservation->getOriginal('status') !== ParkingReservationStatus::BOOKED->value) {
            $this->queueConfirmation->handleParking($reservation);
        }
    }
}
