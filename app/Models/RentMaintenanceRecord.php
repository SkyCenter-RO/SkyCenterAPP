<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentMaintenanceRecord extends Model
{
    protected $fillable = [
        'rent_vehicle_id', 'service_at', 'mileage_at_service', 'intervention_type',
        'next_service_km', 'details', 'metadata',
    ];

    protected $casts = [
        'service_at' => 'datetime',
        'mileage_at_service' => 'integer',
        'next_service_km' => 'integer',
        'metadata' => 'array',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(RentVehicle::class, 'rent_vehicle_id');
    }
}
