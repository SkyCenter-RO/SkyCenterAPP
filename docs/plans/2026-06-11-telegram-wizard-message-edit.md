# Telegram Wizard Dynamic Message Edit — Implementation Plan

> **For Antigravity:** REQUIRED WORKFLOW: Use `.agent/workflows/execute-plan.md` to execute this plan in single-flow mode.

**Goal:** Make the Telegram income and expense wizards edit an existing bot message (inline keyboard) instead of flooding the chat with new messages at every wizard step.

**Architecture:** When n8n receives a `callback_query` update, the payload includes a `message_id` identifying the bot's message that contains the inline keyboard. We capture that `message_id` into `telegram_sessions.wizard_message_id` immediately after the session is loaded or created. The `transition` and `noChange` helpers then check whether `wizard_message_id` is set and return `action: 'edit'` (to call Telegram's `editMessageText` API) or `action: 'send'` for the initial prompt and the final confirmation message. Tests are updated to verify both the new `'action'` value and the stored `wizard_message_id`.

**Tech Stack:** PHP 8.2, Laravel 11, PHPUnit / Pest, Docker (app container), SQLite (test DB), TelegramSession Eloquent model.

---

### Task 1: Fix `wizard_message_id` capture — Income Wizard

**Files:**
- Modify: `app/Actions/Telegram/ProcessIncomeTelegramUpdate.php`

**Step 1: Write the failing test — assert `wizard_message_id` is stored after callback**

In `tests/Feature/Telegram/IncomeTelegramWizardTest.php`, add at the bottom before the closing brace:

```php
public function test_callback_query_stores_wizard_message_id(): void
{
    $this->incomePost($this->msg('start')); // creates session
    $res = $this->incomePost($this->cb('service:parking')); // callback_query with message_id=50
    $res->assertOk();

    $session = TelegramSession::first();
    $this->assertEquals(50, $session->wizard_message_id);
}

public function test_subsequent_transition_returns_edit_action(): void
{
    $this->incomePost($this->msg('start'));
    $res = $this->incomePost($this->cb('service:parking'));
    $res->assertOk();
    $this->assertEquals('edit', $res->json('action'));
    $this->assertEquals(50, $res->json('message_id'));
}

public function test_text_input_after_callback_also_returns_edit_action(): void
{
    $this->incomePost($this->msg('start'));
    $this->incomePost($this->cb('service:parking')); // sets wizard_message_id=50
    $res = $this->incomePost($this->msg('BX 1234 AB')); // text update
    $res->assertOk();
    $this->assertEquals('edit', $res->json('action'));
    $this->assertEquals(50, $res->json('message_id'));
}

public function test_final_save_returns_send_action(): void
{
    $this->incomePost($this->msg('start'));
    $this->incomePost($this->cb('service:parking'));
    $this->incomePost($this->msg('BX 1234 AB'));
    $this->incomePost($this->msg('250'));
    $res = $this->incomePost($this->cb('payment:cash'));
    $res->assertOk();
    // Final confirmation is always a new message (no wizard_message_id)
    $this->assertEquals('send', $res->json('action'));
    $this->assertNull($res->json('message_id'));
}
```

**Step 2: Run the new tests to verify they fail**

```bash
docker compose exec -T app php artisan test --filter="test_callback_query_stores_wizard_message_id|test_subsequent_transition_returns_edit_action|test_text_input_after_callback_also_returns_edit_action|test_final_save_returns_send_action" tests/Feature/Telegram/IncomeTelegramWizardTest.php
```

Expected: FAIL (wizard_message_id is null, action is 'send').

**Step 3: Implement the fix in `ProcessIncomeTelegramUpdate::handle()`**

In the `handle()` method, extract `message_id` and save it to the session when `update_type === 'callback_query'`:

```php
public function handle(array $update): array
{
    $chatId    = $update['chat_id'];
    $userId    = $update['user_id'];
    $type      = $update['update_type'];
    $text      = trim($update['text'] ?? '');
    $cbData    = $update['callback_data'] ?? null;
    $username  = $update['username'] ?? null;
    $messageId = $update['message_id'] ?? null;   // ← ADD THIS

    $session = TelegramSession::where('chat_id', $chatId)
        ->where('user_id', $userId)
        ->first();

    // Expired or missing session → reset
    $expired = false;
    if ($session && $session->isExpired()) {
        $session->delete();
        $session = null;
        $expired = true;
    }

    if (! $session) {
        $session = TelegramSession::create([
            'chat_id'    => $chatId,
            'user_id'    => $userId,
            'username'   => $username,
            'group_type' => 'income',
            'state'      => 'selecting_service',
            'data'       => [],
            'expires_at' => now()->addMinutes(30),
        ]);

        return $this->selectServicePrompt($session, $expired);
    }

    // Refresh expiry on every interaction
    $session->expires_at = now()->addMinutes(30);

    // ── Capture wizard_message_id from callback_query ──────────────────────
    if ($type === 'callback_query' && $messageId) {
        $session->wizard_message_id = $messageId;
        $session->save();
    }
    // ──────────────────────────────────────────────────────────────────────

    return match ($session->state) {
        'selecting_service'  => $this->handleSelectService($session, $type, $cbData),
        'waiting_plate'      => $this->handleWaitingPlate($session, $type, $text),
        'selecting_property' => $this->handleSelectProperty($session, $type, $cbData),
        'selecting_rooms'    => $this->handleSelectRooms($session, $type, $cbData),
        'waiting_rent_desc'  => $this->handleWaitingRentDesc($session, $type, $text),
        'waiting_amount'     => $this->handleWaitingAmount($session, $type, $text),
        'selecting_payment'  => $this->handleSelectPayment($session, $type, $cbData),
        default              => $this->selectServicePrompt($session),
    };
}
```

**Step 4: Update `transition()` and `noChange()` to use dynamic action**

Replace the two private helper methods:

```php
private function transition(TelegramSession $s, string $state, string $text, ?array $keyboard = null): array
{
    $s->state = $state;
    $s->save();

    $hasMsgId = ! empty($s->wizard_message_id);

    return [
        'action'     => $hasMsgId ? 'edit' : 'send',
        'chat_id'    => $s->chat_id,
        'message_id' => $s->wizard_message_id,
        'text'       => $text,
        'keyboard'   => $keyboard,
    ];
}

private function noChange(TelegramSession $s, string $text, ?array $keyboard = null): array
{
    $s->save();

    $hasMsgId = ! empty($s->wizard_message_id);

    return [
        'action'     => $hasMsgId ? 'edit' : 'send',
        'chat_id'    => $s->chat_id,
        'message_id' => $s->wizard_message_id,
        'text'       => $text,
        'keyboard'   => $keyboard,
    ];
}
```

**Step 5: Run just the income wizard tests to confirm they all pass**

```bash
docker compose exec -T app php artisan test tests/Feature/Telegram/IncomeTelegramWizardTest.php
```

Expected: all tests PASS.

**Step 6: Commit**

```bash
git add app/Actions/Telegram/ProcessIncomeTelegramUpdate.php tests/Feature/Telegram/IncomeTelegramWizardTest.php
git commit -m "feat(telegram): capture wizard_message_id and return edit action for income wizard"
```

---

### Task 2: Fix `wizard_message_id` capture — Expense Wizard

**Files:**
- Modify: `app/Actions/Telegram/ProcessExpenseTelegramUpdate.php`
- Modify: `tests/Feature/Telegram/ExpenseTelegramWizardTest.php`

**Step 1: Write the failing tests for the expense wizard**

In `tests/Feature/Telegram/ExpenseTelegramWizardTest.php`, add:

```php
public function test_callback_query_stores_wizard_message_id_for_expense(): void
{
    $this->expensePost($this->msg('start')); // creates session
    $res = $this->expensePost($this->cb('category:1')); // callback_query with message_id=50 -- may need a seeded category
    $res->assertOk();

    // wizard_message_id should be stored
    $session = TelegramSession::first();
    $this->assertEquals(50, $session->wizard_message_id);
}

public function test_category_selection_returns_edit_action(): void
{
    // Seed a budget category first
    \App\Models\BudgetCategory::create([
        'name' => 'Test Cat', 'service' => 'general', 'kind' => 'expense',
        'frequency' => 'once', 'emoji' => '🧪', 'is_active' => true,
    ]);
    $cat = \App\Models\BudgetCategory::first();

    $this->expensePost($this->msg('start'));
    $res = $this->expensePost($this->cb("category:{$cat->id}"));
    $res->assertOk();
    $this->assertEquals('edit', $res->json('action'));
    $this->assertEquals(50, $res->json('message_id'));
}

public function test_amount_input_after_callback_returns_edit_action(): void
{
    \App\Models\BudgetCategory::create([
        'name' => 'Test Cat', 'service' => 'general', 'kind' => 'expense',
        'frequency' => 'once', 'emoji' => '🧪', 'is_active' => true,
    ]);
    $cat = \App\Models\BudgetCategory::first();

    $this->expensePost($this->msg('start'));
    $this->expensePost($this->cb("category:{$cat->id}")); // sets wizard_message_id=50
    $res = $this->expensePost($this->msg('300'));           // text update
    $res->assertOk();
    // Still edit – wizard_message_id is retained
    $this->assertEquals('edit', $res->json('action'));
    $this->assertEquals(50, $res->json('message_id'));
}
```

**Step 2: Run new tests to confirm they fail**

```bash
docker compose exec -T app php artisan test --filter="test_callback_query_stores_wizard_message_id_for_expense|test_category_selection_returns_edit_action|test_amount_input_after_callback_returns_edit_action" tests/Feature/Telegram/ExpenseTelegramWizardTest.php
```

Expected: FAIL.

**Step 3: Implement fix in `ProcessExpenseTelegramUpdate::handle()`**

Add `$messageId` extraction and session save (same pattern as income):

```php
public function handle(array $update): array
{
    $chatId    = $update['chat_id'];
    $userId    = $update['user_id'];
    $type      = $update['update_type'];
    $text      = trim($update['text'] ?? '');
    $cbData    = $update['callback_data'] ?? null;
    $username  = $update['username'] ?? null;
    $messageId = $update['message_id'] ?? null;   // ← ADD THIS

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
            'chat_id'    => $chatId,
            'user_id'    => $userId,
            'username'   => $username,
            'group_type' => 'expense',
            'state'      => 'selecting_category',
            'data'       => [],
            'expires_at' => now()->addMinutes(30),
        ]);

        return $this->categoryPrompt($session, $expired);
    }

    $session->expires_at = now()->addMinutes(30);

    // ── Capture wizard_message_id from callback_query ──────────────────────
    if ($type === 'callback_query' && $messageId) {
        $session->wizard_message_id = $messageId;
        $session->save();
    }
    // ──────────────────────────────────────────────────────────────────────

    return match ($session->state) {
        'selecting_category'     => $this->handleSelectCategory($session, $type, $cbData),
        'waiting_custom_desc'    => $this->handleCustomDesc($session, $type, $text),
        'waiting_expense_amount' => $this->handleAmount($session, $type, $text),
        default                  => $this->categoryPrompt($session),
    };
}
```

**Step 4: Update `transition()` and `noChange()` in the expense action**

```php
private function transition(TelegramSession $s, string $state, string $text, ?array $keyboard = null): array
{
    $s->state = $state;
    $s->save();

    $hasMsgId = ! empty($s->wizard_message_id);

    return [
        'action'     => $hasMsgId ? 'edit' : 'send',
        'chat_id'    => $s->chat_id,
        'message_id' => $s->wizard_message_id,
        'text'       => $text,
        'keyboard'   => $keyboard,
    ];
}

private function noChange(TelegramSession $s, string $text, ?array $keyboard = null): array
{
    $s->save();

    $hasMsgId = ! empty($s->wizard_message_id);

    return [
        'action'     => $hasMsgId ? 'edit' : 'send',
        'chat_id'    => $s->chat_id,
        'message_id' => $s->wizard_message_id,
        'text'       => $text,
        'keyboard'   => $keyboard,
    ];
}
```

Note: Update all callers of `noChange` and `transition` inside `ProcessExpenseTelegramUpdate` to pass an optional `$keyboard` argument where needed (they were previously single-line with no keyboard arg; the signature now accepts it optionally, so existing callers don't break).

**Step 5: Run all expense wizard tests**

```bash
docker compose exec -T app php artisan test tests/Feature/Telegram/ExpenseTelegramWizardTest.php
```

Expected: all tests PASS.

**Step 6: Commit**

```bash
git add app/Actions/Telegram/ProcessExpenseTelegramUpdate.php tests/Feature/Telegram/ExpenseTelegramWizardTest.php
git commit -m "feat(telegram): capture wizard_message_id and return edit action for expense wizard"
```

---

### Task 3: Full Test Suite Verification

**Step 1: Run the full test suite**

```bash
docker compose exec -T app php artisan test
```

Expected: ALL tests pass (159 + new tests). Zero failures.

**Step 2: Update task.md tracker**

Mark all tasks complete in `docs/plans/task.md`.

**Step 3: Commit docs**

```bash
git add docs/plans/task.md docs/plans/2026-06-11-telegram-wizard-message-edit.md
git commit -m "docs: add telegram wizard message-edit implementation plan and update task tracker"
```
