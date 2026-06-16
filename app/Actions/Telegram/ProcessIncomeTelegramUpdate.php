<?php

namespace App\Actions\Telegram;

use App\Models\BudgetRawMessage;
use App\Models\BudgetTransaction;
use App\Models\LodgingProperty;
use App\Models\TelegramSession;
use Illuminate\Support\Str;

class ProcessIncomeTelegramUpdate
{
    public function handle(array $update): array
    {
        $chatId = $update['chat_id'];
        $userId = $update['user_id'];
        $type = $update['update_type'];
        $text = trim($update['text'] ?? '');
        $cbData = $update['callback_data'] ?? null;
        $username = $update['username'] ?? null;
        $messageId = $update['message_id'] ?? null;

        $expired = false;
        $isNew = false;

        $session = \DB::transaction(function () use ($chatId, $userId, $username, &$expired, &$isNew) {
            $session = TelegramSession::where('chat_id', $chatId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($session && $session->isExpired()) {
                $session->delete();
                $session = null;
                $expired = true;
            }

            if (! $session) {
                $session = TelegramSession::create([
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                    'username' => $username,
                    'group_type' => 'income',
                    'state' => 'selecting_service',
                    'data' => [],
                    'expires_at' => now()->addMinutes(30),
                ]);
                $isNew = true;
            }

            return $session;
        });

        if ($isNew) {
            return $this->selectServicePrompt($session, $expired);
        }

        // Refresh expiry on every interaction
        $session->expires_at = now()->addMinutes(30);

        // Capture the bot's message_id from callback_query so we can edit it later
        if ($type === 'callback_query' && $messageId) {
            $session->wizard_message_id = $messageId;
            $session->save();
        }

        return match ($session->state) {
            'selecting_service' => $this->handleSelectService($session, $type, $cbData),
            'waiting_plate' => $this->handleWaitingPlate($session, $type, $text),
            'selecting_property' => $this->handleSelectProperty($session, $type, $cbData),
            'selecting_rooms' => $this->handleSelectRooms($session, $type, $cbData),
            'waiting_rent_desc' => $this->handleWaitingRentDesc($session, $type, $text),
            'waiting_amount' => $this->handleWaitingAmount($session, $type, $text),
            'selecting_payment' => $this->handleSelectPayment($session, $type, $cbData),
            default => $this->selectServicePrompt($session),
        };
    }

    // ── State handlers ─────────────────────────────────────────────────────────

    private function handleSelectService(TelegramSession $s, string $type, ?string $cbData): array
    {
        if ($type !== 'callback_query' || ! str_starts_with((string) $cbData, 'service:')) {
            return $this->selectServicePrompt($s);
        }

        $service = substr($cbData, 8); // 'parking' | 'hotel' | 'rent'

        $s->mergeData(['service' => $service]);

        return match ($service) {
            'parking' => $this->transition($s, 'waiting_plate',
                '🚗 Introdu numărul de înmatriculare:'),
            'hotel' => $this->transition($s, 'selecting_property',
                '🏨 Selectează proprietatea:', $this->propertyKeyboard()),
            'rent' => $this->transition($s, 'waiting_rent_desc',
                '🚙 Descriere scurtă (client / vehicul):'),
            default => $this->selectServicePrompt($s),
        };
    }

    private function handleWaitingPlate(TelegramSession $s, string $type, string $text): array
    {
        if ($type !== 'message' || $text === '') {
            return $this->noChange($s, '🚗 Introdu numărul de înmatriculare:');
        }
        $s->mergeData(['plate' => mb_substr($text, 0, 32)]);

        return $this->transition($s, 'waiting_amount', '💵 Sumă încasată?');
    }

    private function handleSelectProperty(TelegramSession $s, string $type, ?string $cbData): array
    {
        if ($type !== 'callback_query' || ! str_starts_with((string) $cbData, 'property:')) {
            return $this->noChange($s, '🏨 Selectează proprietatea:', $this->propertyKeyboard());
        }
        $propertySlug = substr($cbData, 9); // 'skycenter' | 'serafim'
        $property = LodgingProperty::where('is_active', true)->get()
            ->first(fn ($p) => Str::slug($p->name) === $propertySlug);

        if (! $property) {
            return $this->noChange($s, '🏨 Selectează proprietatea:', $this->propertyKeyboard());
        }

        $rooms = $property->rooms()->where('is_active', true)->pluck('name')->toArray();
        $s->mergeData(['property' => $property->name, 'rooms_available' => $rooms, 'rooms' => []]);

        return $this->transition($s, 'selecting_rooms',
            '🛏 Camera(ele) — bifează și apasă ✅:',
            $this->roomsKeyboard($rooms, []));
    }

    private function handleSelectRooms(TelegramSession $s, string $type, ?string $cbData): array
    {
        $available = $s->data['rooms_available'] ?? [];
        $selected = $s->data['rooms'] ?? [];

        if ($type === 'callback_query' && $cbData === 'rooms:confirm') {
            if (empty($selected)) {
                return $this->noChange($s,
                    '⚠️ Selectează cel puțin o cameră.',
                    $this->roomsKeyboard($available, $selected));
            }

            return $this->transition($s, 'waiting_amount', '💵 Sumă totală?');
        }

        if ($type === 'callback_query' && str_starts_with((string) $cbData, 'room:')) {
            $room = substr($cbData, 5);
            if (in_array($room, $selected)) {
                $selected = array_values(array_diff($selected, [$room]));
            } elseif (in_array($room, $available)) {
                $selected[] = $room;
            }
            $s->mergeData(['rooms' => $selected]);
            $s->save();

            return $this->noChange($s,
                '🛏 Camera(ele) — bifează și apasă ✅:',
                $this->roomsKeyboard($available, $selected));
        }

        return $this->noChange($s,
            '🛏 Camera(ele) — bifează și apasă ✅:',
            $this->roomsKeyboard($available, $selected));
    }

    private function handleWaitingRentDesc(TelegramSession $s, string $type, string $text): array
    {
        if ($type !== 'message' || $text === '') {
            return $this->noChange($s, '🚙 Descriere scurtă (client / vehicul):');
        }
        $s->mergeData(['description' => mb_substr($text, 0, 128)]);

        return $this->transition($s, 'waiting_amount', '💵 Sumă?');
    }

    private function handleWaitingAmount(TelegramSession $s, string $type, string $text): array
    {
        if ($type !== 'message') {
            return $this->noChange($s, '💵 Introdu suma:');
        }
        $normalized = str_replace(',', '.', $text);
        if (! preg_match('/^\d{1,8}(\.\d{1,2})?$/', $normalized)) {
            return $this->noChange($s,
                '❌ Sumă invalidă. Introdu un număr (ex: 250 sau 75.50):');
        }
        $s->mergeData(['amount' => (float) $normalized]);

        return $this->transition($s, 'selecting_payment',
            '💳 Metoda de plată:', $this->paymentKeyboard());
    }

    private function handleSelectPayment(TelegramSession $s, string $type, ?string $cbData): array
    {
        $methods = [
            'cash' => 'Cash RON',
            'card' => 'Card RON',
            'eur' => 'EUR cash',
            'usd' => 'USD cash',
            'transfer' => 'Transfer',
        ];
        if ($type !== 'callback_query' || ! str_starts_with((string) $cbData, 'payment:')) {
            return $this->noChange($s, '💳 Metoda de plată:', $this->paymentKeyboard());
        }
        $method = substr($cbData, 8);
        if (! array_key_exists($method, $methods)) {
            return $this->noChange($s, '💳 Metoda de plată:', $this->paymentKeyboard());
        }

        $data = $s->data;
        $service = $data['service'];
        $amount = (float) ($data['amount']);
        $currency = match ($method) {
            'eur' => 'EUR',
            'usd' => 'USD',
            default => 'RON',
        };
        $paymentMethod = match ($method) {
            'card' => 'card',
            'transfer' => 'transfer',
            default => 'cash',
        };

        $description = match ($service) {
            'parking' => 'Parcare '.($data['plate'] ?? ''),
            'hotel' => ($data['property'] ?? 'Hotel').' '.implode('+', $data['rooms'] ?? []),
            'rent' => 'Rent-a-car: '.($data['description'] ?? ''),
            default => $service,
        };

        // Save raw message
        $raw = BudgetRawMessage::create([
            'chat_id' => $s->chat_id,
            'message_id' => 'bot-session-'.$s->id,
            'text' => "[INCOME] {$description} - {$amount} {$currency} ({$paymentMethod})",
            'parsed' => true,
            'received_at' => now(),
        ]);

        // Save transaction
        $serviceMap = ['parking' => 'parcare', 'hotel' => 'hotel', 'rent' => 'rent'];
        BudgetTransaction::create([
            'type' => 'income',
            'service' => $serviceMap[$service] ?? $service,
            'amount' => $amount,
            'currency' => $currency,
            'occurred_on' => now()->toDateString(),
            'description' => $description,
            'telegram_chat' => 'income',
            'raw_message_id' => $raw->id,
            'metadata' => [
                'payment_method' => $paymentMethod,
                'telegram_user' => $s->username,
                'wizard_data' => $data,
            ],
        ]);

        $chatId = $s->chat_id;
        $s->delete();

        return [
            'action' => 'send',
            'chat_id' => $chatId,
            'message_id' => null,
            'text' => "✅ Salvat!\n{$description} — {$amount} {$currency} (".$methods[$method].")\nData: ".now()->format('d.m.Y H:i'),
            'keyboard' => null,
        ];
    }

    // ── Keyboard builders ──────────────────────────────────────────────────────

    private function selectServicePrompt(TelegramSession $s, bool $expired = false): array
    {
        $s->state = 'selecting_service';
        $s->data = [];
        $s->expires_at = now()->addMinutes(30);
        $s->save();

        $prefix = $expired ? "⏰ Sesiunea a expirat. Reîncepem:\n\n" : '';

        return [
            'action' => 'send',
            'chat_id' => $s->chat_id,
            'message_id' => null,
            'text' => $prefix.'💰 Ce tip de încasare înregistrezi?',
            'keyboard' => ['inline_keyboard' => [[
                ['text' => '🚗 Parcare',    'callback_data' => 'service:parking'],
                ['text' => '🏨 Hotel',      'callback_data' => 'service:hotel'],
                ['text' => '🚙 Rent-a-car', 'callback_data' => 'service:rent'],
            ]]],
        ];
    }

    private function propertyKeyboard(): array
    {
        $props = LodgingProperty::where('is_active', true)->get();
        $buttons = $props->map(fn ($p) => [
            'text' => $p->name,
            'callback_data' => 'property:'.Str::slug($p->name),
        ])->toArray();

        return ['inline_keyboard' => [array_values($buttons)]];
    }

    private function roomsKeyboard(array $available, array $selected): array
    {
        $buttons = array_map(fn ($room) => [
            'text' => in_array($room, $selected) ? "☑ {$room}" : $room,
            'callback_data' => "room:{$room}",
        ], $available);

        $rows = array_chunk($buttons, 4);
        $rows[] = [['text' => '✅ Confirmă selecția', 'callback_data' => 'rooms:confirm']];

        return ['inline_keyboard' => $rows];
    }

    private function paymentKeyboard(): array
    {
        return ['inline_keyboard' => [
            [
                ['text' => 'Cash RON',        'callback_data' => 'payment:cash'],
                ['text' => 'Card RON',        'callback_data' => 'payment:card'],
            ],
            [
                ['text' => 'EUR cash',        'callback_data' => 'payment:eur'],
                ['text' => 'USD cash',        'callback_data' => 'payment:usd'],
                ['text' => 'Transfer bancar', 'callback_data' => 'payment:transfer'],
            ],
        ]];
    }

    private function transition(TelegramSession $s, string $state, string $text, ?array $keyboard = null): array
    {
        $s->state = $state;
        $s->save();

        $hasMsgId = ! empty($s->wizard_message_id);

        return [
            'action' => $hasMsgId ? 'edit' : 'send',
            'chat_id' => $s->chat_id,
            'message_id' => $s->wizard_message_id,
            'text' => $text,
            'keyboard' => $keyboard,
        ];
    }

    private function noChange(TelegramSession $s, string $text, ?array $keyboard = null): array
    {
        $s->save();

        $hasMsgId = ! empty($s->wizard_message_id);

        return [
            'action' => $hasMsgId ? 'edit' : 'send',
            'chat_id' => $s->chat_id,
            'message_id' => $s->wizard_message_id,
            'text' => $text,
            'keyboard' => $keyboard,
        ];
    }
}
