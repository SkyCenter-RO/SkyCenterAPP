<?php

namespace Tests\Feature\Panel;

use App\Models\LodgingProperty;
use App\Models\LodgingReservation;
use App\Models\LodgingSyncLink;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LodgingModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_room_and_reservation_relations(): void
    {
        $property = LodgingProperty::create(['name' => 'Sky Center', 'is_active' => true]);
        $room = Room::create(['property_id' => $property->id, 'name' => 'Camera 1', 'is_active' => true]);
        $reservation = LodgingReservation::create([
            'room_id' => $room->id,
            'guest_name' => 'Maria Ionescu',
            'status' => 'confirmed',
            'check_in' => '2026-06-10',
            'check_out' => '2026-06-12',
            'nights' => 2,
            'price' => 350.00,
        ]);
        LodgingSyncLink::create([
            'property_id' => $property->id,
            'channel' => 'booking',
            'ical_url' => 'https://example.com/cal.ics',
        ]);

        $this->assertSame(1, $property->rooms()->count());
        $this->assertSame(1, $property->syncLinks()->count());
        $this->assertSame('Sky Center', $room->property->name);
        $this->assertSame('Camera 1', $reservation->room->name);
        $this->assertEquals('2026-06-10', $reservation->check_in->toDateString());
        $this->assertTrue($room->is_active);
    }
}
