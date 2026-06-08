<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentContract extends Model
{
    protected $fillable = [
        'source', 'external_id', 'contract_code', 'rent_vehicle_id', 'rent_client_id',
        'usage_type', 'start_date', 'end_date', 'km_at_handover', 'km_at_return',
        'daily_price', 'monthly_price', 'warranty_collected', 'total_price', 'currency',
        'status', 'notes', 'metadata', 'created_by_id', 'updated_by_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'km_at_handover' => 'integer',
        'km_at_return' => 'integer',
        'daily_price' => 'decimal:2',
        'monthly_price' => 'decimal:2',
        'warranty_collected' => 'decimal:2',
        'total_price' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(RentVehicle::class, 'rent_vehicle_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(RentClient::class, 'rent_client_id');
    }
}
