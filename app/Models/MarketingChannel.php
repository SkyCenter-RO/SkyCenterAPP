<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingChannel extends Model
{
    protected $fillable = [
        'name', 'channel_type', 'status', 'url', 'account_id',
        'monthly_budget_eur', 'notes', 'last_reviewed_at',
    ];

    protected $casts = [
        'last_reviewed_at'   => 'date',
        'monthly_budget_eur' => 'decimal:2',
    ];
}
