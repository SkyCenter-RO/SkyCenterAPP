<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetRawMessage extends Model
{
    protected $fillable = [
        'chat_id', 'message_id', 'text', 'parsed', 'transaction_id', 'received_at',
    ];

    protected $casts = [
        'parsed' => 'boolean',
        'received_at' => 'datetime',
    ];
}
