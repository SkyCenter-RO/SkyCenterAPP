<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentVehicleImage extends Model
{
    protected $fillable = ['rent_vehicle_id', 'path', 'caption'];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(RentVehicle::class, 'rent_vehicle_id');
    }
}
