<?php

namespace Tests\Feature\Panel;

use App\Models\RentClient;
use App\Models\RentContract;
use App\Models\RentMaintenanceRecord;
use App\Models\RentVehicle;
use App\Models\RentVehicleImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_contract_and_relations(): void
    {
        $vehicle = RentVehicle::create([
            'license_plate' => 'B 99 SKY',
            'brand' => 'Dacia',
            'model_name' => 'Logan',
            'manufacture_year' => 2021,
            'status' => 'available',
            'insurance_12_months' => true,
        ]);
        $client = RentClient::create(['name' => 'Andrei Rusu', 'phone' => '0711111111']);
        $contract = RentContract::create([
            'rent_vehicle_id' => $vehicle->id,
            'rent_client_id' => $client->id,
            'usage_type' => 'rent',
            'status' => 'active',
            'start_date' => '2026-06-08',
            'end_date' => '2026-06-15',
            'total_price' => 700.00,
        ]);
        RentVehicleImage::create(['rent_vehicle_id' => $vehicle->id, 'path' => 'img/car.jpg']);
        RentMaintenanceRecord::create(['rent_vehicle_id' => $vehicle->id, 'intervention_type' => 'schimb ulei']);

        $this->assertSame(1, $vehicle->contracts()->count());
        $this->assertSame(1, $vehicle->images()->count());
        $this->assertSame(1, $vehicle->maintenanceRecords()->count());
        $this->assertSame('Dacia', $contract->vehicle->brand);
        $this->assertSame('Andrei Rusu', $contract->client->name);
        $this->assertTrue($vehicle->insurance_12_months);
    }
}
