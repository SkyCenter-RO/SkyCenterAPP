<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LodgingSyncLink extends Model
{
    protected $fillable = ['property_id', 'room_id', 'channel', 'ical_url', 'last_synced_at', 'is_active'];

    protected $casts = ['last_synced_at' => 'datetime', 'is_active' => 'boolean'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(LodgingProperty::class, 'property_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
}
