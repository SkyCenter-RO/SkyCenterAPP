<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentClient extends Model
{
    protected $fillable = [
        'source', 'external_id', 'name', 'phone', 'normalized_phone', 'email',
        'identity_document', 'notes', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function contracts(): HasMany
    {
        return $this->hasMany(RentContract::class, 'rent_client_id');
    }
}
