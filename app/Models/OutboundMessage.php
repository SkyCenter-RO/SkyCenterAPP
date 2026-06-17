<?php

namespace App\Models;

use App\Enums\OutboundMessageStatus;
use Illuminate\Database\Eloquent\Model;

class OutboundMessage extends Model
{
    protected $fillable = [
        'service', 'reference_id', 'channel', 'template_key', 'payload',
        'scheduled_at', 'sent_at', 'status',
    ];

    protected $casts = [
        'status' => OutboundMessageStatus::class,
        'payload' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];
}
