<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationEvent extends Model
{
    protected $fillable = [
        'webhook_log_id', 'event_type', 'service', 'external_id', 'occurred_at', 'status', 'payload',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'payload' => 'array',
    ];

    public function webhookLog(): BelongsTo
    {
        return $this->belongsTo(AutomationWebhookLog::class, 'webhook_log_id');
    }
}
