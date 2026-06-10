<?php

namespace App\Observers;

use App\Actions\Messaging\QueueConfirmationMessage;
use App\Models\LodgingReservation;

class LodgingReservationObserver
{
    public function __construct(private QueueConfirmationMessage $queueConfirmation)
    {
    }

    public function created(LodgingReservation $reservation): void
    {
        if ($reservation->status === 'confirmed') {
            $this->queueConfirmation->handleLodging($reservation);
        }
    }

    public function updated(LodgingReservation $reservation): void
    {
        if ($reservation->wasChanged('status')
            && $reservation->status === 'confirmed'
            && $reservation->getOriginal('status') !== 'confirmed') {
            $this->queueConfirmation->handleLodging($reservation);
        }
    }
}
