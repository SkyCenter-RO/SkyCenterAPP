<?php

namespace Tests\Feature\Panel;

use App\Filament\Resources\LodgingReservations\Pages\CreateLodgingReservation;
use App\Filament\Resources\RentContracts\Pages\CreateRentContract;
use App\Models\LodgingProperty;
use App\Models\LodgingReservation;
use App\Models\RentClient;
use App\Models\RentContract;
use App\Models\RentVehicle;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DoubleBookingValidationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_lodging_reservation_blocks_overlap(): void
    {
        $admin = $this->admin();
        $property = LodgingProperty::create(['name' => 'Hotel Test', 'is_active' => true]);
        $room = Room::create(['property_id' => $property->id, 'name' => 'Room 101', 'is_active' => true]);

        // Create baseline reservation: June 10 to June 15
        LodgingReservation::create([
            'room_id' => $room->id,
            'guest_name' => 'First Guest',
            'status' => 'confirmed',
            'check_in' => '2026-06-10',
            'check_out' => '2026-06-15',
            'currency' => 'RON',
        ]);

        // 1. Inside overlap: June 11 to June 14 -> Fail
        $this->actingAs($admin);
        Livewire::test(CreateLodgingReservation::class)
            ->fillForm([
                'room_id' => $room->id,
                'check_in' => '2026-06-11',
                'check_out' => '2026-06-14',
                'guest_name' => 'Second Guest',
                'currency' => 'RON',
            ])
            ->call('create')
            ->assertHasErrors(['check_out']);

        // 2. Start overlap: June 08 to June 12 -> Fail
        Livewire::test(CreateLodgingReservation::class)
            ->fillForm([
                'room_id' => $room->id,
                'check_in' => '2026-06-08',
                'check_out' => '2026-06-12',
                'guest_name' => 'Second Guest',
                'currency' => 'RON',
            ])
            ->call('create')
            ->assertHasErrors(['check_out']);

        // 3. End overlap: June 14 to June 18 -> Fail
        Livewire::test(CreateLodgingReservation::class)
            ->fillForm([
                'room_id' => $room->id,
                'check_in' => '2026-06-14',
                'check_out' => '2026-06-18',
                'guest_name' => 'Second Guest',
                'currency' => 'RON',
            ])
            ->call('create')
            ->assertHasErrors(['check_out']);

        // 4. Adjacent after: June 15 to June 20 -> Success (no overlap)
        Livewire::test(CreateLodgingReservation::class)
            ->fillForm([
                'room_id' => $room->id,
                'check_in' => '2026-06-15',
                'check_out' => '2026-06-20',
                'guest_name' => 'Second Guest',
                'currency' => 'RON',
            ])
            ->call('create')
            ->assertHasNoErrors();

        // 5. Adjacent before: June 05 to June 10 -> Success (no overlap)
        Livewire::test(CreateLodgingReservation::class)
            ->fillForm([
                'room_id' => $room->id,
                'check_in' => '2026-06-05',
                'check_out' => '2026-06-10',
                'guest_name' => 'Third Guest',
                'currency' => 'RON',
            ])
            ->call('create')
            ->assertHasNoErrors();
    }

    public function test_rent_contract_blocks_overlap(): void
    {
        $admin = $this->admin();
        $vehicle = RentVehicle::create([
            'license_plate' => 'B 100 TST',
            'brand' => 'Dacia',
            'model_name' => 'Logan',
            'manufacture_year' => 2022,
            'status' => 'available',
        ]);
        $client = RentClient::create(['name' => 'Client test', 'phone' => '0799999999']);

        // Create baseline contract: June 10 to June 15
        RentContract::create([
            'rent_vehicle_id' => $vehicle->id,
            'rent_client_id' => $client->id,
            'usage_type' => 'rent',
            'status' => 'active',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-15',
            'currency' => 'RON',
        ]);

        $this->actingAs($admin);

        // 1. Inside overlap: June 11 to June 14 -> Fail
        Livewire::test(CreateRentContract::class)
            ->fillForm([
                'rent_vehicle_id' => $vehicle->id,
                'rent_client_id' => $client->id,
                'usage_type' => 'rent',
                'start_date' => '2026-06-11',
                'end_date' => '2026-06-14',
                'status' => 'active',
                'currency' => 'RON',
            ])
            ->call('create')
            ->assertHasErrors(['end_date']);

        // 2. Start overlap: June 08 to June 12 -> Fail
        Livewire::test(CreateRentContract::class)
            ->fillForm([
                'rent_vehicle_id' => $vehicle->id,
                'rent_client_id' => $client->id,
                'usage_type' => 'rent',
                'start_date' => '2026-06-08',
                'end_date' => '2026-06-12',
                'status' => 'active',
                'currency' => 'RON',
            ])
            ->call('create')
            ->assertHasErrors(['end_date']);

        // 3. End overlap: June 14 to June 18 -> Fail
        Livewire::test(CreateRentContract::class)
            ->fillForm([
                'rent_vehicle_id' => $vehicle->id,
                'rent_client_id' => $client->id,
                'usage_type' => 'rent',
                'start_date' => '2026-06-14',
                'end_date' => '2026-06-18',
                'status' => 'active',
                'currency' => 'RON',
            ])
            ->call('create')
            ->assertHasErrors(['end_date']);

        // 4. Adjacent after: June 15 to June 20 -> Success
        Livewire::test(CreateRentContract::class)
            ->fillForm([
                'rent_vehicle_id' => $vehicle->id,
                'rent_client_id' => $client->id,
                'usage_type' => 'rent',
                'start_date' => '2026-06-15',
                'end_date' => '2026-06-20',
                'status' => 'active',
                'currency' => 'RON',
            ])
            ->call('create')
            ->assertHasNoErrors();

        // 5. Adjacent before: June 05 to June 10 -> Success
        Livewire::test(CreateRentContract::class)
            ->fillForm([
                'rent_vehicle_id' => $vehicle->id,
                'rent_client_id' => $client->id,
                'usage_type' => 'rent',
                'start_date' => '2026-06-05',
                'end_date' => '2026-06-10',
                'status' => 'active',
                'currency' => 'RON',
            ])
            ->call('create')
            ->assertHasNoErrors();
    }
}
