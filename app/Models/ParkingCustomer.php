<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingCustomer extends Model
{
    protected $fillable = [
        'source', 'external_id', 'name', 'phone', 'normalized_phone', 'email', 'city', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function reservations(): HasMany
    {
        return $this->hasMany(ParkingReservation::class, 'customer_id');
    }
}
