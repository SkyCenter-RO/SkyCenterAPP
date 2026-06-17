<?php

namespace App\Models;

use App\Enums\LodgingReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LodgingReservation extends Model
{
    protected $fillable = [
        'source', 'external_id', 'room_id', 'guest_name', 'phone', 'normalized_phone', 'email',
        'status', 'review_request_sent', 'check_in', 'check_out', 'nights', 'price', 'direct_price',
        'currency', 'source_created_at', 'notes', 'metadata', 'created_by_id', 'updated_by_id',
    ];

    protected $casts = [
        'status' => LodgingReservationStatus::class,
        'check_in' => 'date',
        'check_out' => 'date',
        'review_request_sent' => 'boolean',
        'source_created_at' => 'datetime',
        'nights' => 'integer',
        'price' => 'decimal:2',
        'direct_price' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
}
