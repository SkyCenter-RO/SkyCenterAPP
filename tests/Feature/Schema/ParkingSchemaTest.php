<?php

namespace Tests\Feature\Schema;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParkingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_parking_tables_exist(): void
    {
        foreach (['parking_lots', 'parking_zones', 'parking_spaces', 'parking_customers',
            'parking_prices', 'parking_reservations', 'parking_reservation_images',
            'parking_status_audits'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing $table");
        }
        $this->assertTrue(Schema::hasColumns('parking_reservations', [
            'status', 'plate', 'normalized_plate', 'vehicle_type', 'check_in_at',
            'check_out_at', 'days', 'adults', 'children', 'keys_left', 'cost',
            'quoted_price', 'currency', 'review_request_sent',
        ]));
    }

    public function test_reservation_status_check_rejects_invalid_value(): void
    {
        $this->expectException(QueryException::class);
        DB::table('parking_reservations')->insert(['status' => 'flying']);
    }

    public function test_reservation_accepts_valid_status(): void
    {
        $id = DB::table('parking_reservations')->insertGetId([
            'status' => 'pending_approval', 'plate' => 'B123XYZ',
            'vehicle_type' => 'autoturism', 'keys_left' => true,
        ]);
        $this->assertDatabaseHas('parking_reservations', ['id' => $id, 'status' => 'pending_approval']);
    }
}
