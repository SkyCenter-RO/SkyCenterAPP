<?php

namespace Tests\Feature\Panel;

use App\Filament\Pages\OrdineaDeZi;
use App\Filament\Resources\LodgingReservations\LodgingReservationResource;
use App\Filament\Resources\ParkingReservations\ParkingReservationResource;
use App\Filament\Resources\RentContracts\RentContractResource;
use App\Models\LodgingProperty;
use App\Models\LodgingReservation;
use App\Models\ParkingLot;
use App\Models\ParkingReservation;
use App\Models\RentClient;
use App\Models\RentContract;
use App\Models\RentVehicle;
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

        $page = new OrdineaDeZi;
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

        $page = new OrdineaDeZi;
        $page->selectedDate = '2026-06-10';
        $events = $page->getLodgingEvents();

        $this->assertCount(1, $events['checkIns']);
        $this->assertSame('Maria', $events['checkIns'][0]['guest']);
        $this->assertCount(0, $events['checkOuts']);
    }

    public function test_daily_events_link_to_their_source_records(): void
    {
        $lot = ParkingLot::create(['name' => 'Parcarea 1', 'total_spaces' => 54]);
        $parking = ParkingReservation::create([
            'lot_id' => $lot->id,
            'status' => 'booked',
            'plate' => 'IS 10 SKY',
            'check_in_at' => '2026-06-10 09:00:00',
        ]);

        $property = LodgingProperty::create(['name' => 'Sky Center']);
        $room = Room::create(['property_id' => $property->id, 'name' => 'Camera 1', 'is_active' => true]);
        $lodging = LodgingReservation::create([
            'room_id' => $room->id,
            'guest_name' => 'Maria',
            'status' => 'confirmed',
            'check_in' => '2026-06-10',
            'check_out' => '2026-06-12',
        ]);

        $vehicle = RentVehicle::create([
            'license_plate' => 'IS 20 SKY',
            'brand' => 'Dacia',
            'model_name' => 'Logan',
            'status' => 'available',
        ]);
        $client = RentClient::create(['name' => 'Ion Pop']);
        $contract = RentContract::create([
            'rent_vehicle_id' => $vehicle->id,
            'rent_client_id' => $client->id,
            'usage_type' => 'rent',
            'status' => 'active',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
        ]);

        $page = new OrdineaDeZi;
        $page->selectedDate = '2026-06-10';

        $this->assertSame(
            ParkingReservationResource::getUrl('edit', ['record' => $parking], panel: 'admin'),
            $page->getParkingEvents()['checkIns'][0]['url'],
        );
        $this->assertSame(
            LodgingReservationResource::getUrl('edit', ['record' => $lodging], panel: 'admin'),
            $page->getLodgingEvents()['checkIns'][0]['url'],
        );
        $this->assertSame(
            RentContractResource::getUrl('edit', ['record' => $contract], panel: 'admin'),
            $page->getRentEvents()['checkIns'][0]['url'],
        );
    }
}
