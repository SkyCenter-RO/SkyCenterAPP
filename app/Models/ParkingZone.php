<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingZone extends Model
{
    protected $fillable = ['lot_id', 'code', 'capacity'];

    protected $casts = ['capacity' => 'integer'];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(ParkingSpace::class, 'zone_id');
    }
}
