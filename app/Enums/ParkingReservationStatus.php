<?php

namespace App\Enums;

enum ParkingReservationStatus: string
{
    case PENDING_APPROVAL = 'pending_approval';
    case BOOKED = 'booked';
    case PARKED = 'parked';
    case DEPARTED = 'departed';
    case CANCELLED = 'cancelled';
}
