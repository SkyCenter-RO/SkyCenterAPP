<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingReview extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'platform', 'vertical', 'score', 'review_count',
        'recorded_on', 'notes', 'created_by_id',
    ];

    protected $casts = [
        'recorded_on' => 'date',
        'score' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
