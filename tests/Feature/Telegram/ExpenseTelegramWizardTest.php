<?php

namespace Tests\Feature\Telegram;

use App\Models\BudgetCategory;
use App\Models\TelegramSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ExpenseTelegramWizardTest extends TestCase
{
    use RefreshDatabase;

    private function expensePost(array $payload): TestResponse
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer '.config('skycenter.automation_api_token'),
        ])->postJson('/api/automation/telegram/expense', $payload);
    }

    private function msg(string $text, string $chatId = '-100222', string $userId = '2001'): array
    {
        return [
            'update_type' => 'message',
            'chat_id' => $chatId,
            'user_id' => $userId,
            'username' => 'TestUser',
            'message_id' => rand(1, 9999),
            'text' => $text,
            'callback_query_id' => null,
            'callback_data' => null,
        ];
    }

    private function cb(string $data, string $chatId = '-100222', string $userId = '2001'): array
    {
        return [
            'update_type' => 'callback_query',
            'chat_id' => $chatId,
            'user_id' => $userId,
            'username' => 'TestUser',
            'message_id' => 50,
            'text' => null,
            'callback_query_id' => 'cbq456',
            'callback_data' => $data,
        ];
    }

    public function test_fresh_message_shows_category_selection(): void
    {
        $res = $this->expensePost($this->msg('hello'));
        $res->assertOk();
        $this->assertEquals('send', $res->json('action'));
        $this->assertStringContainsString('categor', strtolower($res->json('text')));
        $this->assertNotNull($res->json('keyboard'));
        $this->assertEquals('selecting_category', TelegramSession::first()->state);
    }

    public function test_standard_category_selection_prompts_amount(): void
    {
        $this->expensePost($this->msg('start'));
        // Setup budget category
        $cat = BudgetCategory::create([
            'service' => 'general',
            'name' => 'Kaufland',
            'kind' => 'expense',
            'frequency' => 'once',
            'currency' => 'RON',
            'is_active' => true,
        ]);
        $res = $this->expensePost($this->cb("category:{$cat->id}"));
        $res->assertOk();
        $this->assertStringContainsString('sumă', strtolower($res->json('text')));
        $this->assertEquals('waiting_expense_amount', TelegramSession::first()->state);
    }

    public function test_custom_category_prompts_description(): void
    {
        $this->expensePost($this->msg('start'));
        $res = $this->expensePost($this->cb('category:custom'));
        $res->assertOk();
        $this->assertStringContainsString('descriere', strtolower($res->json('text')));
        $this->assertEquals('waiting_custom_desc', TelegramSession::first()->state);
    }

    public function test_custom_description_then_amount(): void
    {
        $this->expensePost($this->msg('start'));
        $this->expensePost($this->cb('category:custom'));
        $res = $this->expensePost($this->msg('700 uși'));
        $res->assertOk();
        $this->assertEquals('waiting_expense_amount', TelegramSession::first()->state);
        $this->assertEquals('700 uși', TelegramSession::first()->data['custom_desc']);
    }

    public function test_amount_saves_expense_transaction(): void
    {
        $this->expensePost($this->msg('start'));
        $cat = BudgetCategory::create([
            'service' => 'general',
            'name' => 'Curățenie',
            'kind' => 'expense',
            'frequency' => 'once',
            'currency' => 'RON',
            'is_active' => true,
        ]);
        $this->expensePost($this->cb("category:{$cat->id}"));
        $res = $this->expensePost($this->msg('300'));
        $res->assertOk();
        $this->assertStringContainsString('salvat', strtolower($res->json('text')));
        $this->assertDatabaseMissing('telegram_sessions', ['chat_id' => '-100222']);
        $this->assertDatabaseHas('budget_transactions', [
            'type' => 'expense',
            'category_id' => $cat->id,
            'amount' => 300.00,
            'currency' => 'RON',
        ]);
    }

    public function test_invalid_amount_keeps_state(): void
    {
        $this->expensePost($this->msg('start'));
        $cat = BudgetCategory::create([
            'service' => 'general',
            'name' => 'Kaufland',
            'kind' => 'expense',
            'frequency' => 'once',
            'currency' => 'RON',
            'is_active' => true,
        ]);
        $this->expensePost($this->cb("category:{$cat->id}"));
        $res = $this->expensePost($this->msg('doua sute'));
        $res->assertOk();
        $this->assertStringContainsString('invalid', strtolower($res->json('text')));
        $this->assertEquals('waiting_expense_amount', TelegramSession::first()->state);
    }

    public function test_expired_session_resets(): void
    {
        TelegramSession::create([
            'chat_id' => '-100222',
            'user_id' => '2001',
            'username' => 'TestUser',
            'group_type' => 'expense',
            'state' => 'waiting_expense_amount',
            'data' => ['category_id' => 1, 'category_name' => 'Kaufland'],
            'expires_at' => now()->subMinutes(5),
        ]);
        $res = $this->expensePost($this->msg('300'));
        $res->assertOk();
        $this->assertEquals('selecting_category', TelegramSession::first()->state);
        $this->assertStringContainsString('expirat', strtolower($res->json('text')));
    }

    public function test_missing_token_returns_401(): void
    {
        $res = $this->postJson('/api/automation/telegram/expense', $this->msg('hello'));
        $res->assertStatus(401);
    }

    public function test_comma_decimal_amount_accepted(): void
    {
        $this->expensePost($this->msg('start'));
        $cat = BudgetCategory::create([
            'service' => 'general',
            'name' => 'Kaufland',
            'kind' => 'expense',
            'frequency' => 'once',
            'currency' => 'RON',
            'is_active' => true,
        ]);
        $this->expensePost($this->cb("category:{$cat->id}"));
        $res = $this->expensePost($this->msg('151,20'));
        $res->assertOk();
        $this->assertDatabaseHas('budget_transactions', ['amount' => 151.20]);
    }

    public function test_callback_query_stores_wizard_message_id_for_expense(): void
    {
        $this->expensePost($this->msg('start')); // creates session
        $res = $this->expensePost($this->cb('category:custom')); // callback_query with message_id=50
        $res->assertOk();

        $session = TelegramSession::first();
        $this->assertEquals(50, $session->wizard_message_id);
    }

    public function test_category_selection_returns_edit_action(): void
    {
        $cat = BudgetCategory::create([
            'service' => 'general', 'name' => 'Test Cat', 'kind' => 'expense',
            'frequency' => 'once', 'emoji' => '🧪', 'is_active' => true,
        ]);

        $this->expensePost($this->msg('start'));
        $res = $this->expensePost($this->cb("category:{$cat->id}"));
        $res->assertOk();
        $this->assertEquals('edit', $res->json('action'));
        $this->assertEquals(50, $res->json('message_id'));
    }

    public function test_amount_input_after_callback_returns_edit_action(): void
    {
        $cat = BudgetCategory::create([
            'service' => 'general', 'name' => 'Test Cat', 'kind' => 'expense',
            'frequency' => 'once', 'emoji' => '🧪', 'is_active' => true,
        ]);

        $this->expensePost($this->msg('start'));
        $this->expensePost($this->cb("category:{$cat->id}")); // sets wizard_message_id=50
        $res = $this->expensePost($this->msg('300'));           // text update — still retains wizard_message_id
        $res->assertOk();
        // Final save always returns 'send' (session deleted, clean confirmation)
        $this->assertEquals('send', $res->json('action'));
        $this->assertNull($res->json('message_id'));
    }
}
