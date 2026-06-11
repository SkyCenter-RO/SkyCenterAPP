<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingContentCalendar extends Model
{
    protected $table = 'marketing_content_calendar';

    protected $fillable = [
        'title', 'platform', 'vertical', 'content_type', 'language',
        'status', 'scheduled_at', 'published_at', 'copy_text', 'notes', 'created_by_id',
    ];

    protected $casts = [
        'scheduled_at' => 'date',
        'published_at' => 'date',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
