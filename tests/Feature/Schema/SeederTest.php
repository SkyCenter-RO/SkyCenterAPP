<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_parking_lots_and_zones(): void
    {
        $this->seed(\Database\Seeders\ParkingReferenceSeeder::class);

        $this->assertDatabaseHas('parking_lots', ['name' => 'Parcarea 1', 'total_spaces' => 54]);
        $this->assertDatabaseHas('parking_lots', ['name' => 'Parcarea 2', 'total_spaces' => 30]);
        // zones A-E for Parcarea 1
        $this->assertSame(5, DB::table('parking_zones')->count());
        $this->assertSame(54, (int) DB::table('parking_zones')->sum('capacity'));
    }

    public function test_seeds_two_properties_with_rooms(): void
    {
        $this->seed(\Database\Seeders\LodgingReferenceSeeder::class);

        $this->assertDatabaseHas('lodging_properties', ['name' => 'Sky Center']);
        $this->assertDatabaseHas('lodging_properties', ['name' => 'Serafim']);
        $this->assertSame(12, DB::table('rooms')->count()); // 7 + 5
    }
}
