<?php

namespace Tests\Feature\Panel;

use App\Models\ParkingCustomer;
use App\Models\ParkingLot;
use App\Models\ParkingReservation;
use App\Models\ParkingReservationImage;
use App\Models\ParkingStatusAudit;
use App\Models\ParkingZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lot_has_zones_and_reservation_relations_work(): void
    {
        $lot = ParkingLot::create(['name' => 'Parcarea 1', 'total_spaces' => 54]);
        $zone = ParkingZone::create(['lot_id' => $lot->id, 'code' => 'A', 'capacity' => 11]);
        $customer = ParkingCustomer::create(['name' => 'Ion Popescu', 'phone' => '0700000000']);

        $reservation = ParkingReservation::create([
            'customer_id' => $customer->id,
            'lot_id' => $lot->id,
            'zone_id' => $zone->id,
            'status' => 'booked',
            'plate' => 'B 123 ABC',
            'vehicle_type' => 'autoturism',
            'keys_left' => true,
            'metadata' => ['note' => 'test'],
        ]);

        ParkingReservationImage::create(['parking_reservation_id' => $reservation->id, 'path' => 'img/1.jpg']);
        ParkingStatusAudit::create(['parking_reservation_id' => $reservation->id, 'to_status' => 'booked']);

        $this->assertSame(1, $lot->zones()->count());
        $this->assertSame('Parcarea 1', $reservation->lot->name);
        $this->assertSame('Ion Popescu', $reservation->customer->name);
        $this->assertSame('A', $reservation->zone->code);
        $this->assertSame(1, $reservation->images()->count());
        $this->assertSame(1, $reservation->statusAudits()->count());
        $this->assertTrue($reservation->keys_left);
        $this->assertSame(['note' => 'test'], $reservation->metadata);
    }
}
