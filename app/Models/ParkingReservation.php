<?php

namespace App\Models;

use App\Enums\ParkingReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingReservation extends Model
{
    protected $fillable = [
        'source', 'external_id', 'customer_id', 'lot_id', 'zone_id', 'parking_space_id',
        'status', 'plate', 'normalized_plate', 'vehicle_type', 'check_in_at', 'check_out_at',
        'days', 'adults', 'children', 'keys_left', 'cost', 'quoted_price', 'currency',
        'paid', 'notes', 'review_request_sent', 'source_created_at', 'metadata',
        'created_by_id', 'updated_by_id',
    ];

    protected $casts = [
        'status' => ParkingReservationStatus::class,
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'source_created_at' => 'datetime',
        'days' => 'decimal:2',
        'adults' => 'integer',
        'children' => 'integer',
        'keys_left' => 'boolean',
        'paid' => 'boolean',
        'review_request_sent' => 'boolean',
        'cost' => 'decimal:2',
        'quoted_price' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(ParkingCustomer::class, 'customer_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ParkingZone::class, 'zone_id');
    }

    public function parkingSpace(): BelongsTo
    {
        return $this->belongsTo(ParkingSpace::class, 'parking_space_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ParkingReservationImage::class, 'parking_reservation_id');
    }

    public function statusAudits(): HasMany
    {
        return $this->hasMany(ParkingStatusAudit::class, 'parking_reservation_id');
    }
}
