<?php

namespace Tests\Feature\Messaging;

use App\Models\LodgingProperty;
use App\Models\LodgingReservation;
use App\Models\MessageTemplate;
use App\Models\OutboundMessage;
use App\Models\ParkingCustomer;
use App\Models\ParkingReservation;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmationMessageTest extends TestCase
{
    use RefreshDatabase;

    private function seedParkingTemplate(): void
    {
        MessageTemplate::create([
            'source' => 'manual',
            'template_key' => 'confirmation',
            'service' => 'parking',
            'channel' => 'whatsapp',
            'locale' => 'ro',
            'body' => 'Buna {{name}}, auto {{plate}}, {{check_in}} - {{check_out}}.',
            'is_active' => true,
        ]);
    }

    private function seedLodgingTemplate(): void
    {
        MessageTemplate::create([
            'source' => 'manual',
            'template_key' => 'confirmation',
            'service' => 'lodging',
            'channel' => 'whatsapp',
            'locale' => 'ro',
            'body' => 'Buna {{guest_name}}, {{property}} ({{room}}), {{check_in}} - {{check_out}}.',
            'is_active' => true,
        ]);
    }

    public function test_parking_transition_to_booked_queues_confirmation(): void
    {
        $this->seedParkingTemplate();

        $customer = ParkingCustomer::create([
            'source' => 'manual',
            'name' => 'Ion Pop',
            'normalized_phone' => '0722111222',
        ]);

        $reservation = ParkingReservation::create([
            'source' => 'manual',
            'external_id' => 'P-1',
            'customer_id' => $customer->id,
            'status' => 'pending_approval',
            'plate' => 'B 123 ABC',
            'check_in_at' => '2026-07-01 10:00:00',
            'check_out_at' => '2026-07-05 10:00:00',
        ]);

        $reservation->update(['status' => 'booked']);

        $message = OutboundMessage::query()->where('service', 'parking')->where('reference_id', $reservation->id)->first();
        $this->assertNotNull($message);
        $this->assertSame('pending', $message->status->value);
        $this->assertSame('confirmation', $message->template_key);
        $this->assertSame('whatsapp', $message->channel);
        $this->assertSame('Buna Ion Pop, auto B 123 ABC, 01.07.2026 10:00 - 05.07.2026 10:00.', $message->payload['text']);
        $this->assertSame('0722111222', $message->payload['contact']);

        $this->assertDatabaseHas('automation_events', [
            'event_type' => 'confirmation_queued',
            'service' => 'parking',
            'external_id' => 'P-1',
        ]);
    }

    public function test_parking_created_directly_as_booked_queues_confirmation(): void
    {
        $this->seedParkingTemplate();

        $customer = ParkingCustomer::create([
            'source' => 'manual',
            'name' => 'Maria Ionescu',
            'normalized_phone' => '0733111222',
        ]);

        $reservation = ParkingReservation::create([
            'source' => 'manual',
            'external_id' => 'P-2',
            'customer_id' => $customer->id,
            'status' => 'booked',
            'plate' => 'CJ 99 XYZ',
            'check_in_at' => '2026-07-10 09:00:00',
            'check_out_at' => '2026-07-12 09:00:00',
        ]);

        $this->assertSame(
            1,
            OutboundMessage::query()->where('service', 'parking')->where('reference_id', $reservation->id)->count()
        );
    }

    public function test_parking_transition_to_other_status_does_not_queue(): void
    {
        $this->seedParkingTemplate();

        $customer = ParkingCustomer::create([
            'source' => 'manual',
            'name' => 'Ana Dumitru',
            'normalized_phone' => '0744111222',
        ]);

        $reservation = ParkingReservation::create([
            'source' => 'manual',
            'external_id' => 'P-3',
            'customer_id' => $customer->id,
            'status' => 'booked',
            'plate' => 'B 1 AAA',
            'check_in_at' => '2026-07-01 10:00:00',
            'check_out_at' => '2026-07-05 10:00:00',
        ]);

        OutboundMessage::query()->where('service', 'parking')->where('reference_id', $reservation->id)->delete();

        $reservation->update(['status' => 'parked']);

        $this->assertSame(
            0,
            OutboundMessage::query()->where('service', 'parking')->where('reference_id', $reservation->id)->count()
        );
    }

    public function test_lodging_transition_to_other_status_does_not_queue(): void
    {
        $this->seedLodgingTemplate();

        $property = LodgingProperty::create(['source' => 'manual', 'name' => 'Sky Center']);
        $room = Room::create(['source' => 'manual', 'property_id' => $property->id, 'name' => 'Camera 2']);

        $reservation = LodgingReservation::create([
            'source' => 'manual',
            'external_id' => 'L-2',
            'room_id' => $room->id,
            'guest_name' => 'Elena Vasile',
            'normalized_phone' => '0766222333',
            'status' => 'confirmed',
            'check_in' => '2026-08-01',
            'check_out' => '2026-08-05',
        ]);

        OutboundMessage::query()->where('service', 'lodging')->where('reference_id', $reservation->id)->delete();

        $reservation->update(['status' => 'checked_in']);

        $this->assertSame(
            0,
            OutboundMessage::query()->where('service', 'lodging')->where('reference_id', $reservation->id)->count()
        );
    }

    public function test_lodging_transition_to_confirmed_queues_confirmation(): void
    {
        $this->seedLodgingTemplate();

        $property = LodgingProperty::create(['source' => 'manual', 'name' => 'Sky Center']);
        $room = Room::create(['source' => 'manual', 'property_id' => $property->id, 'name' => 'Camera 1']);

        $reservation = LodgingReservation::create([
            'source' => 'manual',
            'external_id' => 'L-1',
            'room_id' => $room->id,
            'guest_name' => 'Andrei Pop',
            'normalized_phone' => '0755111222',
            'status' => 'pending',
            'check_in' => '2026-08-01',
            'check_out' => '2026-08-05',
        ]);

        $reservation->update(['status' => 'confirmed']);

        $message = OutboundMessage::query()->where('service', 'lodging')->where('reference_id', $reservation->id)->first();
        $this->assertNotNull($message);
        $this->assertSame('confirmation', $message->template_key);
        $this->assertStringContainsString('Andrei Pop', $message->payload['text']);
        $this->assertStringContainsString('Sky Center', $message->payload['text']);
        $this->assertStringContainsString('Camera 1', $message->payload['text']);

        $this->assertDatabaseHas('automation_events', [
            'event_type' => 'confirmation_queued',
            'service' => 'lodging',
            'external_id' => 'L-1',
        ]);
    }

    public function test_missing_template_logs_event_without_creating_message(): void
    {
        $customer = ParkingCustomer::create([
            'source' => 'manual',
            'name' => 'Vasile Ion',
            'normalized_phone' => '0766111222',
        ]);

        $reservation = ParkingReservation::create([
            'source' => 'manual',
            'external_id' => 'P-4',
            'customer_id' => $customer->id,
            'status' => 'booked',
            'plate' => 'B 2 BBB',
            'check_in_at' => '2026-07-01 10:00:00',
            'check_out_at' => '2026-07-05 10:00:00',
        ]);

        $this->assertSame(
            0,
            OutboundMessage::query()->where('service', 'parking')->where('reference_id', $reservation->id)->count()
        );

        $this->assertDatabaseHas('automation_events', [
            'event_type' => 'message_template_missing',
            'service' => 'parking',
            'external_id' => 'P-4',
        ]);
    }

    public function test_missing_contact_logs_event_without_creating_message(): void
    {
        $this->seedParkingTemplate();

        $customer = ParkingCustomer::create([
            'source' => 'manual',
            'name' => 'Cristian Marin',
            'normalized_phone' => null,
            'email' => null,
        ]);

        $reservation = ParkingReservation::create([
            'source' => 'manual',
            'external_id' => 'P-5',
            'customer_id' => $customer->id,
            'status' => 'booked',
            'plate' => 'B 3 CCC',
            'check_in_at' => '2026-07-01 10:00:00',
            'check_out_at' => '2026-07-05 10:00:00',
        ]);

        $this->assertSame(
            0,
            OutboundMessage::query()->where('service', 'parking')->where('reference_id', $reservation->id)->count()
        );

        $this->assertDatabaseHas('automation_events', [
            'event_type' => 'message_contact_missing',
            'service' => 'parking',
            'external_id' => 'P-5',
        ]);
    }
}
