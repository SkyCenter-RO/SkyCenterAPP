<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationWebhookLog extends Model
{
    protected $fillable = [
        'endpoint', 'idempotency_key', 'status', 'http_status', 'event_type', 'service',
        'external_id', 'payload', 'response_body', 'error_message', 'received_at', 'processed_at',
    ];

    protected $casts = [
        'status' => \App\Enums\AutomationWebhookLogStatus::class,
        'http_status' => 'integer',
        'payload' => 'array',
        'response_body' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(AutomationEvent::class, 'webhook_log_id');
    }
}
