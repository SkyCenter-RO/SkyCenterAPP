<?php

namespace Tests\Feature\Schema;

use Database\Seeders\LodgingReferenceSeeder;
use Database\Seeders\MessageTemplateSeeder;
use Database\Seeders\ParkingReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_parking_lots_and_zones(): void
    {
        $this->seed(ParkingReferenceSeeder::class);

        $this->assertDatabaseHas('parking_lots', ['name' => 'Parcarea 1', 'total_spaces' => 54]);
        $this->assertDatabaseHas('parking_lots', ['name' => 'Parcarea 2', 'total_spaces' => 30]);
        // zones A-E for Parcarea 1
        $this->assertSame(5, DB::table('parking_zones')->count());
        $this->assertSame(54, (int) DB::table('parking_zones')->sum('capacity'));
    }

    public function test_seeds_two_properties_with_rooms(): void
    {
        $this->seed(LodgingReferenceSeeder::class);

        $this->assertDatabaseHas('lodging_properties', ['name' => 'Sky Center']);
        $this->assertDatabaseHas('lodging_properties', ['name' => 'Serafim']);
        $this->assertSame(12, DB::table('rooms')->count()); // 7 + 5
    }

    public function test_seeds_four_message_templates(): void
    {
        $this->seed(MessageTemplateSeeder::class);

        $this->assertSame(4, DB::table('message_templates')->count());

        foreach ([
            ['service' => 'parking', 'template_key' => 'confirmation'],
            ['service' => 'lodging', 'template_key' => 'confirmation'],
            ['service' => 'parking', 'template_key' => 'review_request'],
            ['service' => 'lodging', 'template_key' => 'review_request'],
        ] as $expected) {
            $this->assertDatabaseHas('message_templates', $expected + [
                'source' => 'manual',
                'locale' => 'ro',
                'channel' => 'whatsapp',
                'is_active' => true,
            ]);
        }
    }
}
