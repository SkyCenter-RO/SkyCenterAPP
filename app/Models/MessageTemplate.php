<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    protected $fillable = [
        'source', 'external_id', 'template_key', 'service', 'channel', 'locale',
        'label', 'body', 'is_active', 'metadata',
    ];

    protected $casts = ['is_active' => 'boolean', 'metadata' => 'array'];
}
