<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'source', 'external_id', 'service', 'parking_reservation_id', 'lodging_reservation_id',
        'rent_contract_id', 'amount', 'currency', 'method', 'paid_at', 'notes', 'metadata',
        'created_by_id', 'updated_by_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function parkingReservation(): BelongsTo
    {
        return $this->belongsTo(ParkingReservation::class, 'parking_reservation_id');
    }

    public function lodgingReservation(): BelongsTo
    {
        return $this->belongsTo(LodgingReservation::class, 'lodging_reservation_id');
    }

    public function rentContract(): BelongsTo
    {
        return $this->belongsTo(RentContract::class, 'rent_contract_id');
    }

    public function changeAudits(): HasMany
    {
        return $this->hasMany(PaymentChangeAudit::class, 'payment_id');
    }
}
