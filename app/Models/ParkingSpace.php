<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingSpace extends Model
{
    protected $fillable = [
        'source', 'external_id', 'lot_id', 'zone_id', 'label', 'requires_keys',
        'vehicle_type_suitability', 'blocks_space_id', 'blocked_by_space_id',
        'xy_map_location', 'notes', 'metadata',
    ];

    protected $casts = [
        'requires_keys' => 'boolean',
        'metadata' => 'array',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ParkingZone::class, 'zone_id');
    }
}
