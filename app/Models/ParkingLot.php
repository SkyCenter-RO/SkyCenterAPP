<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingLot extends Model
{
    protected $fillable = ['name', 'total_spaces', 'notes'];

    protected $casts = ['total_spaces' => 'integer'];

    public function zones(): HasMany
    {
        return $this->hasMany(ParkingZone::class, 'lot_id');
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(ParkingSpace::class, 'lot_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(ParkingReservation::class, 'lot_id');
    }
}
