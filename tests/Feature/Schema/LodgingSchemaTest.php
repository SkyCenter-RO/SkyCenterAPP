<?php

namespace Tests\Feature\Schema;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LodgingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_lodging_tables_exist(): void
    {
        foreach (['lodging_properties', 'rooms', 'lodging_reservations', 'lodging_sync_links'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing $table");
        }
        $this->assertTrue(Schema::hasColumns('lodging_reservations', [
            'guest_name', 'phone', 'email', 'status', 'check_in', 'check_out',
            'nights', 'price', 'direct_price', 'currency', 'source', 'review_request_sent',
        ]));
    }

    public function test_reservation_status_check_rejects_invalid_value(): void
    {
        $this->expectException(QueryException::class);
        DB::table('lodging_reservations')->insert(['status' => 'teleported']);
    }

    public function test_sync_link_channel_check_rejects_invalid_value(): void
    {
        $this->expectException(QueryException::class);
        DB::table('lodging_sync_links')->insert(['channel' => 'expedia', 'ical_url' => 'http://x']);
    }
}
