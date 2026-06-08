<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = ['source', 'external_id', 'property_id', 'name', 'is_active', 'notes', 'metadata'];

    protected $casts = ['is_active' => 'boolean', 'metadata' => 'array'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(LodgingProperty::class, 'property_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(LodgingReservation::class, 'room_id');
    }
}
