<?php

namespace Tests\Feature\Panel;

use App\Models\ParkingCustomer;
use App\Models\ParkingLot;
use App\Models\ParkingPrice;
use App\Models\ParkingReservation;
use App\Models\ParkingReservationImage;
use App\Models\ParkingSpace;
use App\Models\ParkingStatusAudit;
use App\Models\ParkingZone;
use Carbon\CarbonInterface;
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

    public function test_parking_space_casts_and_lot_zone_relations_work(): void
    {
        $lot = ParkingLot::create(['name' => 'Parcarea 2', 'total_spaces' => 20]);
        $zone = ParkingZone::create(['lot_id' => $lot->id, 'code' => 'B', 'capacity' => 8]);

        $space = ParkingSpace::create([
            'lot_id' => $lot->id,
            'zone_id' => $zone->id,
            'label' => 'B-07',
            'requires_keys' => true,
            'metadata' => ['covered' => true],
        ])->fresh();

        $this->assertTrue($space->requires_keys);
        $this->assertSame(['covered' => true], $space->metadata);
        $this->assertTrue($space->lot->is($lot));
        $this->assertTrue($space->zone->is($zone));
        $this->assertTrue($lot->spaces->contains($space));
        $this->assertTrue($zone->spaces->contains($space));
    }

    public function test_parking_price_casts_numeric_and_metadata_values(): void
    {
        $price = ParkingPrice::create([
            'vehicle_type' => 'autoturism',
            'min_days' => 2,
            'max_days' => 5,
            'price_per_day' => 45.5,
            'fixed_price' => 180,
            'metadata' => ['season' => 'summer'],
        ])->fresh();

        $this->assertSame(2, $price->min_days);
        $this->assertSame(5, $price->max_days);
        $this->assertSame('45.50', $price->price_per_day);
        $this->assertSame('180.00', $price->fixed_price);
        $this->assertSame(['season' => 'summer'], $price->metadata);
    }

    public function test_reservation_decimal_datetime_and_reverse_relations_work(): void
    {
        $lot = ParkingLot::create(['name' => 'Parcarea 3', 'total_spaces' => 12]);
        $customer = ParkingCustomer::create(['name' => 'Maria Ionescu']);
        $reservation = ParkingReservation::create([
            'customer_id' => $customer->id,
            'lot_id' => $lot->id,
            'status' => 'booked',
            'vehicle_type' => 'SUV',
            'check_in_at' => '2026-06-10 08:15:00+03:00',
            'check_out_at' => '2026-06-12 18:30:00+03:00',
            'days' => 2.5,
            'cost' => 225,
            'quoted_price' => 230.4,
        ])->fresh();
        $image = ParkingReservationImage::create([
            'parking_reservation_id' => $reservation->id,
            'path' => 'img/2.jpg',
        ]);
        $audit = ParkingStatusAudit::create([
            'parking_reservation_id' => $reservation->id,
            'to_status' => 'booked',
            'changed_at' => '2026-06-08 14:00:00+03:00',
        ]);

        $this->assertInstanceOf(CarbonInterface::class, $reservation->check_in_at);
        $this->assertInstanceOf(CarbonInterface::class, $reservation->check_out_at);
        $this->assertInstanceOf(CarbonInterface::class, $audit->fresh()->changed_at);
        $this->assertSame('2.50', $reservation->days);
        $this->assertSame('225.00', $reservation->cost);
        $this->assertSame('230.40', $reservation->quoted_price);
        $this->assertTrue($customer->reservations->contains($reservation));
        $this->assertTrue($lot->reservations->contains($reservation));
        $this->assertTrue($image->reservation->is($reservation));
        $this->assertTrue($audit->reservation->is($reservation));
    }
}
