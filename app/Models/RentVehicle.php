<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentVehicle extends Model
{
    protected $fillable = [
        'source', 'external_id', 'license_plate', 'chassis_vin', 'brand', 'model_name',
        'manufacture_year', 'tire_type', 'insurance_start_date', 'insurance_end_date',
        'insurance_12_months', 'itp_date', 'itp_expiry_date', 'current_km',
        'monthly_rent_price', 'daily_rent_price', 'warranty_standard', 'currency',
        'status', 'notes', 'metadata',
    ];

    protected $casts = [
        'manufacture_year' => 'integer',
        'insurance_start_date' => 'date',
        'insurance_end_date' => 'date',
        'insurance_12_months' => 'boolean',
        'itp_date' => 'date',
        'itp_expiry_date' => 'date',
        'current_km' => 'integer',
        'monthly_rent_price' => 'decimal:2',
        'daily_rent_price' => 'decimal:2',
        'warranty_standard' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(RentVehicleImage::class, 'rent_vehicle_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(RentContract::class, 'rent_vehicle_id');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(RentMaintenanceRecord::class, 'rent_vehicle_id');
    }
}
