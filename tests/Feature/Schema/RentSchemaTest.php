<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_rent_tables_exist(): void
    {
        foreach (['rent_vehicles', 'rent_vehicle_images', 'rent_clients',
                  'rent_contracts', 'rent_maintenance_records'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing $table");
        }
        $this->assertTrue(Schema::hasColumns('rent_vehicles', [
            'license_plate', 'chassis_vin', 'brand', 'model_name', 'manufacture_year',
            'tire_type', 'insurance_start_date', 'insurance_end_date', 'insurance_12_months',
            'itp_date', 'itp_expiry_date', 'current_km', 'monthly_rent_price',
            'daily_rent_price', 'warranty_standard', 'status',
        ]));
        $this->assertTrue(Schema::hasColumns('rent_contracts', [
            'contract_code', 'usage_type', 'start_date', 'end_date', 'km_at_handover',
            'km_at_return', 'daily_price', 'monthly_price', 'warranty_collected',
            'total_price', 'status',
        ]));
    }

    public function test_vehicle_status_check_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('rent_vehicles')->insert(['status' => 'crashed']);
    }

    public function test_contract_usage_type_check_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('rent_contracts')->insert(['usage_type' => 'taxi', 'status' => 'active']);
    }
}
