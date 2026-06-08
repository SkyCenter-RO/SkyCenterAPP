<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParkingPrice extends Model
{
    protected $fillable = [
        'source', 'external_id', 'vehicle_type', 'min_days', 'max_days',
        'price_per_day', 'fixed_price', 'currency', 'metadata',
    ];

    protected $casts = [
        'min_days' => 'integer',
        'max_days' => 'integer',
        'price_per_day' => 'decimal:2',
        'fixed_price' => 'decimal:2',
        'metadata' => 'array',
    ];
}
