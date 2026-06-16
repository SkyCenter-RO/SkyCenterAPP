<?php

namespace Tests\Feature\Panel;

use App\Models\LodgingProperty;
use App\Models\LodgingReservation;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_links_to_reservation_and_audits(): void
    {
        $property = LodgingProperty::create(['name' => 'Serafim']);
        $room = Room::create(['property_id' => $property->id, 'name' => 'Camera 1']);
        $reservation = LodgingReservation::create(['room_id' => $room->id, 'guest_name' => 'X']);

        $payment = Payment::create([
            'service' => 'lodging',
            'lodging_reservation_id' => $reservation->id,
            'amount' => 350.00,
            'method' => 'cash',
        ]);

        $this->assertSame('lodging', $payment->service);
        $this->assertSame($reservation->id, $payment->lodgingReservation->id);
        $this->assertSame(1, $payment->changeAudits()->count());
    }
}
