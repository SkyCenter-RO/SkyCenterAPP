<?php

namespace App\Observers;

use App\Actions\Messaging\QueueConfirmationMessage;
use App\Models\ParkingReservation;

class ParkingReservationObserver
{
    public function __construct(private QueueConfirmationMessage $queueConfirmation)
    {
    }

    public function created(ParkingReservation $reservation): void
    {
        if ($reservation->status === 'booked') {
            $this->queueConfirmation->handleParking($reservation);
        }
    }

    public function updated(ParkingReservation $reservation): void
    {
        if ($reservation->wasChanged('status')
            && $reservation->status === 'booked'
            && $reservation->getOriginal('status') !== 'booked') {
            $this->queueConfirmation->handleParking($reservation);
        }
    }
}
