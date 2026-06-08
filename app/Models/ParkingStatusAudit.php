<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingStatusAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'parking_reservation_id', 'user_id', 'from_status', 'to_status', 'changed_at', 'notes',
    ];

    protected $casts = ['changed_at' => 'datetime'];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ParkingReservation::class, 'parking_reservation_id');
    }
}
