<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampaign extends Model
{
    protected $fillable = [
        'name', 'platform', 'vertical', 'status',
        'budget_eur', 'spend_eur', 'conversions',
        'cpc_eur', 'roas', 'period_month', 'notes', 'created_by_id',
    ];

    protected $casts = [
        'period_month' => 'date',
        'budget_eur'   => 'decimal:2',
        'spend_eur'    => 'decimal:2',
        'cpc_eur'      => 'decimal:4',
        'roas'         => 'decimal:2',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function spendLogs(): HasMany
    {
        return $this->hasMany(MarketingAdSpendLog::class, 'campaign_id');
    }
}
