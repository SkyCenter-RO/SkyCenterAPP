<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetTransaction extends Model
{
    protected $fillable = [
        'source', 'external_id', 'type', 'category_id', 'service', 'amount', 'currency',
        'occurred_on', 'description', 'telegram_chat', 'raw_message_id', 'metadata', 'created_by_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_on' => 'date',
        'metadata' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class, 'category_id');
    }

    public function rawMessage(): BelongsTo
    {
        return $this->belongsTo(BudgetRawMessage::class, 'raw_message_id');
    }
}
