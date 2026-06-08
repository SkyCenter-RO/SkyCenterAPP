<?php

namespace Tests\Feature\Panel;

use App\Filament\Pages\OrdineaDeZi;
use App\Models\LodgingProperty;
use App\Models\LodgingReservation;
use App\Models\ParkingLot;
use App\Models\ParkingReservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdineaDeZiTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        return User::factory()->create(['role' => 'operator', 'is_active' => true]);
    }

    public function test_page_renders_for_authenticated_user(): void
    {
        $this->actingAs($this->operator())->get('/admin/ordinea-de-zi')->assertSuccessful();
    }

    public function test_parking_availability_counts_active_reservations(): void
    {
        $lot = ParkingLot::create(['name' => 'Parcarea 1', 'total_spaces' => 54]);
        ParkingReservation::create([
            'lot_id' => $lot->id, 'status' => 'parked',
            'check_in_at' => '2026-06-10 09:00:00', 'check_out_at' => '2026-06-12 09:00:00',
        ]);

        $page = new OrdineaDeZi();
        $page->selectedDate = '2026-06-10';
        $snapshot = $page->getParkingAvailability();

        $this->assertSame(54, $snapshot['totalSpaces']);
        $this->assertSame(1, $snapshot['occupied']);
    }

    public function test_lodging_events_list_check_ins_for_selected_day(): void
    {
        $property = LodgingProperty::create(['name' => 'Sky Center']);
        $room = Room::create(['property_id' => $property->id, 'name' => 'Camera 1', 'is_active' => true]);
        LodgingReservation::create([
            'room_id' => $room->id, 'guest_name' => 'Maria', 'status' => 'confirmed',
            'check_in' => '2026-06-10', 'check_out' => '2026-06-12',
        ]);

        $page = new OrdineaDeZi();
        $page->selectedDate = '2026-06-10';
        $events = $page->getLodgingEvents();

        $this->assertCount(1, $events['checkIns']);
        $this->assertSame('Maria', $events['checkIns'][0]['guest']);
        $this->assertCount(0, $events['checkOuts']);
    }
}
