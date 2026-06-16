<?php

namespace Tests\Feature\Panel;

use App\Actions\Automation\UpsertLodgingReservationFromWebhook;
use App\Actions\Automation\UpsertParkingReservationFromWebhook;
use App\Actions\Telegram\ProcessIncomeTelegramUpdate;
use App\Models\AutomationWebhookLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrencyLockingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_upsert_parking_reservation_acquires_lock(): void
    {
        $log = AutomationWebhookLog::create([
            'endpoint' => 'parcare_form',
            'payload' => [],
            'status' => 'pending',
        ]);

        $payload = [
            'event_type' => 'reservation_created',
            'external_id' => 'FORM-9999',
            'check_in_at' => '2026-06-10 08:00:00+03:00',
            'check_out_at' => '2026-06-12 18:00:00+03:00',
            'phone' => '0700000000',
            'name' => 'John Doe',
        ];

        DB::enableQueryLog();

        $action = new UpsertParkingReservationFromWebhook;
        $action->handle($payload, $log);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $hasLockQuery = false;
        foreach ($queries as $query) {
            $sql = strtolower($query['query']);
            if (str_contains($sql, 'parking_reservations') && str_contains($sql, 'for update')) {
                $hasLockQuery = true;
                break;
            }
        }

        $this->assertTrue($hasLockQuery, 'Expected a select query on parking_reservations with FOR UPDATE lock.');
    }

    public function test_upsert_lodging_reservation_acquires_lock(): void
    {
        $log = AutomationWebhookLog::create([
            'endpoint' => 'cazare_form',
            'payload' => [],
            'status' => 'pending',
        ]);

        $payload = [
            'source' => 'booking',
            'external_id' => 'BOOKING-9999',
            'check_in' => '2026-06-10',
            'check_out' => '2026-06-12',
            'guest_name' => 'Jane Doe',
            'phone' => '0700000000',
        ];

        DB::enableQueryLog();

        $action = new UpsertLodgingReservationFromWebhook;
        $action->handle($payload, $log);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $hasLockQuery = false;
        foreach ($queries as $query) {
            $sql = strtolower($query['query']);
            if (str_contains($sql, 'lodging_reservations') && str_contains($sql, 'for update')) {
                $hasLockQuery = true;
                break;
            }
        }

        $this->assertTrue($hasLockQuery, 'Expected a select query on lodging_reservations with FOR UPDATE lock.');
    }

    public function test_telegram_process_income_acquires_lock_on_sessions(): void
    {
        $update = [
            'chat_id' => '123456',
            'user_id' => '987654',
            'update_type' => 'message',
            'text' => '/start',
            'username' => 'testuser',
            'message_id' => 111,
        ];

        DB::enableQueryLog();

        $action = new ProcessIncomeTelegramUpdate;
        $action->handle($update);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $hasLockQuery = false;
        foreach ($queries as $query) {
            $sql = strtolower($query['query']);
            if (str_contains($sql, 'telegram_sessions') && str_contains($sql, 'for update')) {
                $hasLockQuery = true;
                break;
            }
        }

        $this->assertTrue($hasLockQuery, 'Expected a select query on telegram_sessions with FOR UPDATE lock.');
    }
}
