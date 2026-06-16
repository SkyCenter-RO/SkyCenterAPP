<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingAdSpendLog extends Model
{
    protected $fillable = [
        'campaign_id', 'platform', 'vertical',
        'amount_eur', 'spent_on', 'notes', 'created_by_id',
    ];

    protected $casts = [
        'spent_on' => 'date',
        'amount_eur' => 'decimal:2',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
