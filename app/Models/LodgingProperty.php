<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LodgingProperty extends Model
{
    protected $fillable = ['source', 'external_id', 'name', 'slug', 'is_active', 'notes', 'metadata'];

    protected $casts = ['is_active' => 'boolean', 'metadata' => 'array'];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'property_id');
    }

    public function syncLinks(): HasMany
    {
        return $this->hasMany(LodgingSyncLink::class, 'property_id');
    }
}
