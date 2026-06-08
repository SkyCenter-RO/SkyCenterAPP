<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommonSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_operational_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('users', ['phone', 'role', 'is_active']));
    }

    public function test_users_role_check_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('users')->insert([
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'x',
            'role' => 'wizard', 'is_active' => true,
        ]);
    }

    public function test_messaging_tables_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('message_templates', ['template_key', 'channel', 'body', 'is_active']));
        $this->assertTrue(Schema::hasColumns('outbound_messages', ['channel', 'scheduled_at', 'status']));
    }

    public function test_outbound_message_status_check_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('outbound_messages')->insert([
            'service' => 'parking', 'channel' => 'whatsapp', 'scheduled_at' => now(), 'status' => 'exploded',
        ]);
    }

    public function test_automation_tables_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('automation_webhook_logs', ['endpoint', 'status', 'payload']));
        $this->assertTrue(Schema::hasColumns('automation_events', ['event_type', 'status', 'payload']));
    }
}
