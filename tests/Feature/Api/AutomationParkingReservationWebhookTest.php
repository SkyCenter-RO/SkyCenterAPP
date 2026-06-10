<?php

namespace Tests\Feature\Api;

use App\Models\AutomationEvent;
use App\Models\AutomationWebhookLog;
use App\Models\ParkingCustomer;
use App\Models\ParkingLot;
use App\Models\ParkingReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationParkingReservationWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function endpoint(): string
    {
        return '/api/automation/parking-reservations';
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.config('skycenter.automation_api_token')];
    }

    public function test_valid_payload_creates_customer_and_reservation(): void
    {
        $lot = ParkingLot::create(['name' => 'Parcarea 1', 'total_spaces' => 54]);

        $payload = [
            'external_id' => 'FORM-1001',
            'name' => 'Andrei Pop',
            'phone' => '+40 722 123 456',
            'email' => 'andrei@example.com',
            'plate' => 'B 123 ABC',
            'vehicle_type' => 'autoturism',
            'lot_id' => $lot->id,
            'check_in_at' => '2026-07-01 10:00:00',
            'check_out_at' => '2026-07-05 10:00:00',
            'adults' => 2,
            'children' => 0,
            'quoted_price' => 270,
            'currency' => 'RON',
        ];

        $response = $this->postJson($this->endpoint(), $payload, $this->headers());

        $response->assertOk();

        $customer = ParkingCustomer::query()->where('normalized_phone', '0722123456')->first();
        $this->assertNotNull($customer);
        $this->assertSame('Andrei Pop', $customer->name);

        $reservation = ParkingReservation::query()
            ->where('source', 'parcare_form')->where('external_id', 'FORM-1001')->first();
        $this->assertNotNull($reservation);
        $this->assertSame('pending_approval', $reservation->status);
        $this->assertSame($customer->id, $reservation->customer_id);
        $this->assertSame($lot->id, $reservation->lot_id);

        $log = AutomationWebhookLog::query()->where('external_id', 'FORM-1001')->first();
        $this->assertNotNull($log);
        $this->assertSame('processed', $log->status);
        $this->assertSame(200, $log->http_status);

        $event = AutomationEvent::query()->where('external_id', 'FORM-1001')->first();
        $this->assertSame('reservation_created', $event->event_type);
        $this->assertSame('parking', $event->service);
    }

    public function test_duplicate_external_id_updates_existing_reservation(): void
    {
        $lot = ParkingLot::create(['name' => 'Parcarea 1', 'total_spaces' => 54]);

        $payload = [
            'external_id' => 'FORM-2002',
            'name' => 'Maria Ionescu',
            'phone' => '0733111222',
            'plate' => 'CJ 99 XYZ',
            'lot_id' => $lot->id,
            'check_in_at' => '2026-07-10 09:00:00',
            'check_out_at' => '2026-07-12 09:00:00',
        ];

        $this->postJson($this->endpoint(), $payload, $this->headers())->assertOk();

        $payload['check_out_at'] = '2026-07-15 09:00:00';
        $this->postJson($this->endpoint(), $payload, $this->headers())->assertOk();

        $this->assertSame(
            1,
            ParkingReservation::query()->where('source', 'parcare_form')->where('external_id', 'FORM-2002')->count()
        );

        $reservation = ParkingReservation::query()->where('external_id', 'FORM-2002')->first();
        $this->assertSame('2026-07-15 09:00:00', $reservation->check_out_at->format('Y-m-d H:i:s'));

        $event = AutomationEvent::query()->where('external_id', 'FORM-2002')->latest('id')->first();
        $this->assertSame('reservation_updated', $event->event_type);
    }

    public function test_unparsed_event_is_logged_as_error_without_creating_reservation(): void
    {
        $payload = [
            'event_type' => 'unparsed',
            'subject' => 'Confirmare rezervare',
            'raw_text' => 'Email body that could not be parsed...',
        ];

        $response = $this->postJson($this->endpoint(), $payload, $this->headers());

        $response->assertStatus(422);

        $this->assertSame(0, ParkingReservation::query()->count());

        $log = AutomationWebhookLog::query()->latest('id')->first();
        $this->assertSame('error', $log->status);
        $this->assertSame(422, $log->http_status);
        $this->assertSame('unparsed', $log->event_type);
    }

    public function test_missing_token_is_rejected(): void
    {
        $response = $this->postJson($this->endpoint(), ['external_id' => 'FORM-3003']);

        $response->assertStatus(401);
        $this->assertSame(0, AutomationWebhookLog::query()->count());
    }

    public function test_default_lot_is_used_when_lot_id_missing(): void
    {
        $lot = ParkingLot::create(['name' => 'Parcarea 1', 'total_spaces' => 54]);
        config(['skycenter.default_parking_lot_id' => $lot->id]);

        $payload = [
            'external_id' => 'FORM-4004',
            'name' => 'Ion Vasile',
            'phone' => '0744555666',
            'check_in_at' => '2026-07-20 10:00:00',
            'check_out_at' => '2026-07-22 10:00:00',
        ];

        $this->postJson($this->endpoint(), $payload, $this->headers())->assertOk();

        $reservation = ParkingReservation::query()->where('external_id', 'FORM-4004')->first();
        $this->assertSame($lot->id, $reservation->lot_id);
    }
}
