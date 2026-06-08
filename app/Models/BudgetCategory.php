<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetCategory extends Model
{
    protected $fillable = [
        'service', 'name', 'kind', 'frequency', 'default_amount', 'currency', 'is_active', 'metadata',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(BudgetTransaction::class, 'category_id');
    }
}
