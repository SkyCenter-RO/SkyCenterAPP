<?php

namespace Tests\Feature\Api;

use App\Models\AutomationEvent;
use App\Models\AutomationWebhookLog;
use App\Models\LodgingReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationLodgingReservationWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function endpoint(): string
    {
        return '/api/automation/lodging-reservations';
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.config('skycenter.automation_api_token')];
    }

    public function test_valid_booking_com_payload_creates_reservation_pending_room_assignment(): void
    {
        $payload = [
            'source' => 'booking_com',
            'external_id' => 'BDC-555',
            'guest_name' => 'John Smith',
            'phone' => '+44 7700 900123',
            'email' => 'john@example.com',
            'check_in' => '2026-08-01',
            'check_out' => '2026-08-05',
            'nights' => 4,
            'price' => 600,
            'currency' => 'EUR',
        ];

        $response = $this->postJson($this->endpoint(), $payload, $this->headers());

        $response->assertOk();

        $reservation = LodgingReservation::query()
            ->where('source', 'booking_com')->where('external_id', 'BDC-555')->first();
        $this->assertNotNull($reservation);
        $this->assertNull($reservation->room_id);
        $this->assertSame('pending', $reservation->status->value);
        $this->assertSame('John Smith', $reservation->guest_name);

        $log = AutomationWebhookLog::query()->where('external_id', 'BDC-555')->first();
        $this->assertSame('processed', $log->status->value);

        $event = AutomationEvent::query()->where('external_id', 'BDC-555')->first();
        $this->assertSame('reservation_created', $event->event_type);
        $this->assertSame('lodging', $event->service);
    }

    public function test_duplicate_external_id_updates_existing_reservation(): void
    {
        $payload = [
            'source' => 'airbnb',
            'external_id' => 'AIR-777',
            'guest_name' => 'Jane Doe',
            'check_in' => '2026-09-01',
            'check_out' => '2026-09-03',
            'nights' => 2,
            'price' => 300,
            'currency' => 'EUR',
        ];

        $this->postJson($this->endpoint(), $payload, $this->headers())->assertOk();

        $payload['check_out'] = '2026-09-04';
        $payload['nights'] = 3;
        $this->postJson($this->endpoint(), $payload, $this->headers())->assertOk();

        $this->assertSame(
            1,
            LodgingReservation::query()->where('source', 'airbnb')->where('external_id', 'AIR-777')->count()
        );

        $reservation = LodgingReservation::query()->where('external_id', 'AIR-777')->first();
        $this->assertSame('2026-09-04', $reservation->check_out->format('Y-m-d'));
        $this->assertSame(3, $reservation->nights);

        $event = AutomationEvent::query()->where('external_id', 'AIR-777')->latest('id')->first();
        $this->assertSame('reservation_updated', $event->event_type);
    }

    public function test_unparsed_event_is_logged_as_error_without_creating_reservation(): void
    {
        $payload = [
            'event_type' => 'unparsed',
            'subject' => 'New booking notification',
            'raw_text' => 'Email body that could not be parsed...',
        ];

        $response = $this->postJson($this->endpoint(), $payload, $this->headers());

        $response->assertStatus(422);

        $this->assertSame(0, LodgingReservation::query()->count());

        $log = AutomationWebhookLog::query()->latest('id')->first();
        $this->assertSame('error', $log->status->value);
        $this->assertSame(422, $log->http_status);
    }

    public function test_missing_token_is_rejected(): void
    {
        $response = $this->postJson($this->endpoint(), ['external_id' => 'AIR-999']);

        $response->assertStatus(401);
        $this->assertSame(0, AutomationWebhookLog::query()->count());
    }
}
