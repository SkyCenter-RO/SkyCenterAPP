<?php

namespace App\Observers;

use App\Actions\Messaging\QueueConfirmationMessage;
use App\Models\LodgingReservation;

class LodgingReservationObserver
{
    public function __construct(private QueueConfirmationMessage $queueConfirmation) {}

    public function created(LodgingReservation $reservation): void
    {
        if ($reservation->status === \App\Enums\LodgingReservationStatus::CONFIRMED) {
            $this->queueConfirmation->handleLodging($reservation);
        }
    }

    public function updated(LodgingReservation $reservation): void
    {
        if ($reservation->wasChanged('status')
            && $reservation->status === \App\Enums\LodgingReservationStatus::CONFIRMED
            && $reservation->getOriginal('status') !== \App\Enums\LodgingReservationStatus::CONFIRMED->value) {
            $this->queueConfirmation->handleLodging($reservation);
        }
    }
}
