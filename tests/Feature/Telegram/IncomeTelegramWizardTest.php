<?php

namespace Tests\Feature\Telegram;

use App\Models\BudgetTransaction;
use App\Models\TelegramSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeTelegramWizardTest extends TestCase
{
    use RefreshDatabase;

    private function incomePost(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . config('skycenter.automation_api_token'),
        ])->postJson('/api/automation/telegram/income', $payload);
    }

    private function msg(string $text, string $chatId = '-100111', string $userId = '1001'): array
    {
        return [
            'update_type' => 'message',
            'chat_id'     => $chatId,
            'user_id'     => $userId,
            'username'    => 'TestUser',
            'message_id'  => rand(1, 9999),
            'text'        => $text,
            'callback_query_id' => null,
            'callback_data'     => null,
        ];
    }

    private function cb(string $data, string $chatId = '-100111', string $userId = '1001'): array
    {
        return [
            'update_type'       => 'callback_query',
            'chat_id'           => $chatId,
            'user_id'           => $userId,
            'username'          => 'TestUser',
            'message_id'        => 50,
            'text'              => null,
            'callback_query_id' => 'cbq123',
            'callback_data'     => $data,
        ];
    }

    public function test_fresh_message_returns_service_selection(): void
    {
        $res = $this->incomePost($this->msg('hello'));
        $res->assertOk();
        $data = $res->json();
        $this->assertEquals('send', $data['action']);
        $this->assertStringContainsString('încasare', strtolower($data['text']));
        $this->assertNotNull($data['keyboard']);
        $this->assertEquals('selecting_service', TelegramSession::first()->state);
    }

    public function test_parking_service_selection_prompts_plate(): void
    {
        $this->incomePost($this->msg('start'));
        $res = $this->incomePost($this->cb('service:parking'));
        $res->assertOk();
        $this->assertStringContainsString('înmatriculare', strtolower($res->json('text')));
        $this->assertEquals('waiting_plate', TelegramSession::first()->state);
    }

    public function test_plate_input_prompts_amount(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:parking'));
        $res = $this->incomePost($this->msg('BX 1234 AB'));
        $res->assertOk();
        $this->assertStringContainsString('sumă', strtolower($res->json('text')));
        $this->assertEquals('waiting_amount', TelegramSession::first()->state);
        $this->assertEquals('BX 1234 AB', TelegramSession::first()->data['plate']);
    }

    public function test_invalid_amount_returns_error_and_keeps_state(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:parking'));
        $this->incomePost($this->msg('BX 1234 AB'));
        $res = $this->incomePost($this->msg('abc'));
        $res->assertOk();
        $this->assertStringContainsString('invalid', strtolower($res->json('text')));
        $this->assertEquals('waiting_amount', TelegramSession::first()->state);
    }

    public function test_valid_amount_prompts_payment_method(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:parking'));
        $this->incomePost($this->msg('BX 1234 AB'));
        $res = $this->incomePost($this->msg('250'));
        $res->assertOk();
        $this->assertStringContainsString('plată', strtolower($res->json('text')));
        $this->assertEquals('selecting_payment', TelegramSession::first()->state);
        $this->assertEquals(250.0, TelegramSession::first()->data['amount']);
    }

    public function test_payment_selection_saves_transaction_and_clears_session(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:parking'));
        $this->incomePost($this->msg('BX 1234 AB'));
        $this->incomePost($this->msg('250'));
        $res = $this->incomePost($this->cb('payment:card'));
        $res->assertOk();
        $this->assertStringContainsString('salvat', strtolower($res->json('text')));
        $this->assertDatabaseMissing('telegram_sessions', ['chat_id' => '-100111']);
        $this->assertDatabaseHas('budget_transactions', [
            'type'    => 'income',
            'service' => 'parcare',
            'amount'  => 250.00,
            'currency' => 'RON',
        ]);
    }

    public function test_hotel_flow_property_then_rooms(): void
    {
        // Setup property & rooms
        $property = \App\Models\LodgingProperty::create(['name' => 'SkyCenter', 'is_active' => true]);
        $property->rooms()->createMany([
            ['name' => 'Camera 3', 'is_active' => true],
            ['name' => 'Camera 5', 'is_active' => true],
        ]);

        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:hotel'));
        $res = $this->incomePost($this->cb('property:skycenter'));
        $res->assertOk();
        $this->assertStringContainsString('camera', strtolower($res->json('text')));
        $this->assertEquals('selecting_rooms', TelegramSession::first()->state);
    }

    public function test_hotel_room_toggle_and_confirm(): void
    {
        // Setup property & rooms
        $property = \App\Models\LodgingProperty::create(['name' => 'SkyCenter', 'is_active' => true]);
        $property->rooms()->createMany([
            ['name' => 'Camera 3', 'is_active' => true],
            ['name' => 'Camera 5', 'is_active' => true],
        ]);

        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:hotel'));
        $this->incomePost($this->cb('property:skycenter'));
        $this->incomePost($this->cb('room:Camera 3'));
        $this->incomePost($this->cb('room:Camera 5'));
        $res = $this->incomePost($this->cb('rooms:confirm'));
        $res->assertOk();
        $this->assertEquals('waiting_amount', TelegramSession::first()->state);
        $rooms = TelegramSession::first()->data['rooms'];
        $this->assertContains('Camera 3', $rooms);
        $this->assertContains('Camera 5', $rooms);
    }

    public function test_hotel_confirm_without_room_returns_warning(): void
    {
        // Setup property & rooms
        $property = \App\Models\LodgingProperty::create(['name' => 'SkyCenter', 'is_active' => true]);
        $property->rooms()->createMany([
            ['name' => 'Camera 3', 'is_active' => true],
        ]);

        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:hotel'));
        $this->incomePost($this->cb('property:skycenter'));
        $res = $this->incomePost($this->cb('rooms:confirm'));
        $res->assertOk();
        $this->assertStringContainsString('selectează', strtolower($res->json('text')));
        $this->assertEquals('selecting_rooms', TelegramSession::first()->state);
    }

    public function test_rent_flow_description_then_amount(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:rent'));
        $res = $this->incomePost($this->msg('Seat Bogdan'));
        $res->assertOk();
        $this->assertEquals('waiting_amount', TelegramSession::first()->state);
        $this->assertEquals('Seat Bogdan', TelegramSession::first()->data['description']);
    }

    public function test_expired_session_resets_wizard(): void
    {
        $session = TelegramSession::create([
            'chat_id'    => '-100111',
            'user_id'    => '1001',
            'username'   => 'TestUser',
            'group_type' => 'income',
            'state'      => 'waiting_amount',
            'data'       => ['service' => 'parking', 'plate' => 'BX1234'],
            'expires_at' => now()->subMinutes(5),
        ]);

        $res = $this->incomePost($this->msg('250'));
        $res->assertOk();
        // Should have reset, not proceeded to payment selection
        $this->assertEquals('selecting_service', TelegramSession::first()->state);
        $this->assertStringContainsString('expirat', strtolower($res->json('text')));
    }

    public function test_missing_token_returns_401(): void
    {
        $res = $this->postJson('/api/automation/telegram/income', $this->msg('hello'));
        $res->assertStatus(401);
    }

    public function test_decimal_amount_with_comma_is_accepted(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:parking'));
        $this->incomePost($this->msg('BX 1234 AB'));
        $res = $this->incomePost($this->msg('222,60'));
        $res->assertOk();
        $this->assertEquals('selecting_payment', TelegramSession::first()->state);
        $this->assertEquals(222.60, TelegramSession::first()->data['amount']);
    }

    public function test_eur_payment_saves_eur_currency(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:parking'));
        $this->incomePost($this->msg('BX 1234 AB'));
        $this->incomePost($this->msg('125'));
        $res = $this->incomePost($this->cb('payment:eur'));
        $res->assertOk();
        $this->assertDatabaseHas('budget_transactions', [
            'currency' => 'EUR',
            'amount'   => 125.00,
        ]);
    }

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
}
