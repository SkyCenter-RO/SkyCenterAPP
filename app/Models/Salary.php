<?php

namespace App\Models;

use App\Enums\SalaryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Salary extends Model
{
    protected $fillable = [
        'user_id', 'employee_name', 'amount', 'currency', 'period_month', 'paid_at', 'status', 'notes',
    ];

    protected $casts = [
        'status' => SalaryStatus::class,
        'amount' => 'decimal:2',
        'period_month' => 'date',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
