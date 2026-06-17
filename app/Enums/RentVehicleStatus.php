<?php

namespace App\Enums;

enum RentVehicleStatus: string
{
    case AVAILABLE = 'available';
    case RENTED = 'rented';
    case SERVICE = 'service';
}
