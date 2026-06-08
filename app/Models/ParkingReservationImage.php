<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingReservationImage extends Model
{
    protected $fillable = ['parking_reservation_id', 'path', 'caption'];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ParkingReservation::class, 'parking_reservation_id');
    }
}
