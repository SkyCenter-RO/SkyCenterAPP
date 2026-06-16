<?php

namespace Tests\Feature\Panel;

use App\Models\BudgetCategory;
use App\Models\BudgetTransaction;
use App\Models\LodgingProperty;
use App\Models\LodgingReservation;
use App\Models\ParkingCustomer;
use App\Models\ParkingLot;
use App\Models\ParkingReservation;
use App\Models\RentClient;
use App\Models\RentContract;
use App\Models\RentVehicle;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function operator(): User
    {
        return User::factory()->create(['role' => 'operator', 'is_active' => true]);
    }

    public function test_parking_reservation_policy(): void
    {
        $admin = $this->admin();
        $operator = $this->operator();

        $lot = ParkingLot::create(['name' => 'Parcarea test', 'total_spaces' => 10]);
        $customer = ParkingCustomer::create(['name' => 'Client test']);
        $reservation = ParkingReservation::create([
            'customer_id' => $customer->id,
            'lot_id' => $lot->id,
            'status' => 'booked',
            'plate' => 'B 100 TST',
            'vehicle_type' => 'autoturism',
        ]);

        // Admins can update and delete
        $this->assertTrue($admin->can('update', $reservation));
        $this->assertTrue($admin->can('delete', $reservation));

        // Operators cannot update or delete
        $this->assertFalse($operator->can('update', $reservation));
        $this->assertFalse($operator->can('delete', $reservation));
    }

    public function test_lodging_reservation_policy(): void
    {
        $admin = $this->admin();
        $operator = $this->operator();

        $property = LodgingProperty::create(['name' => 'Property test', 'is_active' => true]);
        $room = Room::create(['property_id' => $property->id, 'name' => 'Room test', 'is_active' => true]);
        $reservation = LodgingReservation::create([
            'room_id' => $room->id,
            'guest_name' => 'Guest test',
            'status' => 'confirmed',
            'check_in' => '2026-06-10',
            'check_out' => '2026-06-12',
            'nights' => 2,
            'price' => 200.00,
        ]);

        // Admins can update and delete
        $this->assertTrue($admin->can('update', $reservation));
        $this->assertTrue($admin->can('delete', $reservation));

        // Operators cannot update or delete
        $this->assertFalse($operator->can('update', $reservation));
        $this->assertFalse($operator->can('delete', $reservation));
    }

    public function test_rent_contract_policy(): void
    {
        $admin = $this->admin();
        $operator = $this->operator();

        $vehicle = RentVehicle::create([
            'license_plate' => 'B 100 TST',
            'brand' => 'Brand test',
            'model_name' => 'Model test',
            'manufacture_year' => 2022,
            'status' => 'available',
        ]);
        $client = RentClient::create(['name' => 'Client test', 'phone' => '0799999999']);
        $contract = RentContract::create([
            'rent_vehicle_id' => $vehicle->id,
            'rent_client_id' => $client->id,
            'usage_type' => 'rent',
            'status' => 'active',
            'start_date' => '2026-06-08',
            'end_date' => '2026-06-15',
            'total_price' => 500.00,
        ]);

        // Admins can update and delete
        $this->assertTrue($admin->can('update', $contract));
        $this->assertTrue($admin->can('delete', $contract));

        // Operators cannot update or delete
        $this->assertFalse($operator->can('update', $contract));
        $this->assertFalse($operator->can('delete', $contract));
    }

    public function test_budget_transaction_policy(): void
    {
        $admin = $this->admin();
        $operator = $this->operator();

        $category = BudgetCategory::create([
            'service' => 'hotel', 'name' => 'Cat test', 'kind' => 'expense', 'frequency' => 'monthly',
        ]);
        $transaction = BudgetTransaction::create([
            'type' => 'expense',
            'category_id' => $category->id,
            'amount' => 100.00,
            'occurred_on' => '2026-06-08',
        ]);

        // Admins can update and delete
        $this->assertTrue($admin->can('update', $transaction));
        $this->assertTrue($admin->can('delete', $transaction));

        // Operators cannot update or delete
        $this->assertFalse($operator->can('update', $transaction));
        $this->assertFalse($operator->can('delete', $transaction));
    }
}
