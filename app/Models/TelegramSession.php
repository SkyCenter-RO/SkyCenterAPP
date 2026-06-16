<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramSession extends Model
{
    protected $fillable = [
        'chat_id',
        'user_id',
        'username',
        'group_type',
        'state',
        'data',
        'wizard_message_id',
        'expires_at',
    ];

    protected $casts = [
        'data' => 'array',
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public static function findOrCreate(string $chatId, string $userId, string $groupType): self
    {
        return static::firstOrCreate(
            ['chat_id' => $chatId, 'user_id' => $userId],
            [
                'group_type' => $groupType,
                'state' => 'start',
                'data' => [],
                'expires_at' => now()->addMinutes(30),
            ]
        );
    }

    public function touchSession(array $merge = []): static
    {
        $this->fill(array_merge(['expires_at' => now()->addMinutes(30)], $merge));
        $this->save();

        return $this;
    }

    public function mergeData(array $data): static
    {
        $this->data = array_merge($this->data ?? [], $data);

        return $this;
    }
}
