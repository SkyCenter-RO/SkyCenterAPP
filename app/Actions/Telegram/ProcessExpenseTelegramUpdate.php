<?php

namespace App\Actions\Telegram;

use App\Models\BudgetCategory;
use App\Models\BudgetRawMessage;
use App\Models\BudgetTransaction;
use App\Models\TelegramSession;

class ProcessExpenseTelegramUpdate
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

        $session = TelegramSession::where('chat_id', $chatId)
            ->where('user_id', $userId)
            ->first();

        $expired = false;
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
                'group_type' => 'expense',
                'state' => 'selecting_category',
                'data' => [],
                'expires_at' => now()->addMinutes(30),
            ]);

            return $this->categoryPrompt($session, $expired);
        }

        $session->expires_at = now()->addMinutes(30);

        // Capture the bot's message_id from callback_query so we can edit it later
        if ($type === 'callback_query' && $messageId) {
            $session->wizard_message_id = $messageId;
            $session->save();
        }

        return match ($session->state) {
            'selecting_category' => $this->handleSelectCategory($session, $type, $cbData),
            'waiting_custom_desc' => $this->handleCustomDesc($session, $type, $text),
            'waiting_expense_amount' => $this->handleAmount($session, $type, $text),
            default => $this->categoryPrompt($session),
        };
    }

    private function handleSelectCategory(TelegramSession $s, string $type, ?string $cbData): array
    {
        if ($type !== 'callback_query') {
            return $this->categoryPrompt($s);
        }

        if ($cbData === 'category:custom') {
            return $this->transition($s, 'waiting_custom_desc', '✏️ Scrie descrierea cheltuielii:');
        }

        if (str_starts_with((string) $cbData, 'category:')) {
            $catId = (int) substr($cbData, 9);
            $cat = BudgetCategory::find($catId);
            if (! $cat) {
                return $this->categoryPrompt($s);
            }
            $s->mergeData(['category_id' => $cat->id, 'category_name' => $cat->name]);

            return $this->transition($s, 'waiting_expense_amount', '💵 Sumă (RON):');
        }

        return $this->categoryPrompt($s);
    }

    private function handleCustomDesc(TelegramSession $s, string $type, string $text): array
    {
        if ($type !== 'message' || $text === '') {
            return $this->noChange($s, '✏️ Scrie descrierea cheltuielii:');
        }
        $s->mergeData(['custom_desc' => mb_substr($text, 0, 128)]);

        return $this->transition($s, 'waiting_expense_amount', '💵 Sumă (RON):');
    }

    private function handleAmount(TelegramSession $s, string $type, string $text): array
    {
        if ($type !== 'message') {
            return $this->noChange($s, '💵 Sumă (RON):');
        }
        $normalized = str_replace(',', '.', $text);
        if (! preg_match('/^\d{1,8}(\.\d{1,2})?$/', $normalized)) {
            return $this->noChange($s, '❌ Sumă invalidă. Introdu un număr (ex: 300 sau 151.20):');
        }

        $amount = (float) $normalized;
        $data = $s->data;
        $catId = $data['category_id'] ?? null;
        $catName = $data['category_name'] ?? ($data['custom_desc'] ?? 'Cheltuială');
        $desc = $data['custom_desc'] ?? $catName;

        $raw = BudgetRawMessage::create([
            'chat_id' => $s->chat_id,
            'message_id' => 'bot-session-'.$s->id,
            'text' => "[EXPENSE] {$desc} - {$amount} RON",
            'parsed' => true,
            'received_at' => now(),
        ]);

        BudgetTransaction::create([
            'type' => 'expense',
            'category_id' => $catId,
            'service' => $catId ? BudgetCategory::find($catId)?->service : 'general',
            'amount' => $amount,
            'currency' => 'RON',
            'occurred_on' => now()->toDateString(),
            'description' => $desc,
            'telegram_chat' => 'expense',
            'raw_message_id' => $raw->id,
            'metadata' => ['telegram_user' => $s->username],
        ]);

        $chatId = $s->chat_id;
        $s->delete();

        return [
            'action' => 'send',
            'chat_id' => $chatId,
            'message_id' => null,
            'text' => "✅ Salvat!\n{$desc} — {$amount} RON\nData: ".now()->format('d.m.Y H:i'),
            'keyboard' => null,
        ];
    }

    private function categoryPrompt(TelegramSession $s, bool $expired = false): array
    {
        $s->state = 'selecting_category';
        $s->data = [];
        $s->expires_at = now()->addMinutes(30);
        $s->save();

        $categories = BudgetCategory::where('is_active', true)
            ->where('kind', 'expense')
            ->orderBy('service')
            ->orderBy('name')
            ->get();

        $buttons = $categories->map(fn ($c) => [
            'text' => ($c->emoji.' '.$c->name),
            'callback_data' => "category:{$c->id}",
        ])->toArray();

        $rows = array_chunk($buttons, 3);
        $rows[] = [['text' => '✏️ Altele...', 'callback_data' => 'category:custom']];

        $prefix = $expired ? "⏰ Sesiunea a expirat. Reîncepem:\n\n" : '';

        return [
            'action' => 'send',
            'chat_id' => $s->chat_id,
            'message_id' => null,
            'text' => $prefix.'📤 Selectează categoria cheltuielii:',
            'keyboard' => ['inline_keyboard' => $rows],
        ];
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
