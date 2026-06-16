<?php

namespace Tests\Feature\Api;

use App\Models\LodgingProperty;
use App\Models\LodgingReservation;
use App\Models\MessageTemplate;
use App\Models\OutboundMessage;
use App\Models\ParkingCustomer;
use App\Models\ParkingReservation;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationDispatchReviewRequestsTest extends TestCase
{
    use RefreshDatabase;

    private function endpoint(): string
    {
        return '/api/automation/dispatch-review-requests';
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.config('skycenter.automation_api_token')];
    }

    private function seedReviewTemplates(): void
    {
        MessageTemplate::create([
            'source' => 'manual', 'template_key' => 'review_request', 'service' => 'parking',
            'channel' => 'whatsapp', 'locale' => 'ro', 'body' => 'Multumim {{name}}!', 'is_active' => true,
        ]);
        MessageTemplate::create([
            'source' => 'manual', 'template_key' => 'review_request', 'service' => 'lodging',
            'channel' => 'whatsapp', 'locale' => 'ro', 'body' => 'Multumim {{guest_name}}!', 'is_active' => true,
        ]);
    }

    public function test_departed_parking_reservation_25h_ago_queues_review_request(): void
    {
        $this->seedReviewTemplates();

        $customer = ParkingCustomer::create([
            'source' => 'manual', 'name' => 'Ion Pop', 'normalized_phone' => '0722111222',
        ]);

        $reservation = ParkingReservation::create([
            'source' => 'manual', 'external_id' => 'P-10', 'customer_id' => $customer->id,
            'status' => 'departed', 'check_in_at' => now()->subDays(3),
            'check_out_at' => now()->subHours(25),
        ]);

        $response = $this->postJson($this->endpoint(), [], $this->headers());

        $response->assertOk();
        $response->assertJson(['parking_queued' => 1, 'lodging_queued' => 0, 'skipped' => 0]);

        $this->assertDatabaseHas('outbound_messages', [
            'service' => 'parking', 'reference_id' => $reservation->id, 'template_key' => 'review_request',
        ]);

        $this->assertTrue($reservation->fresh()->review_request_sent);

        $this->assertDatabaseHas('automation_events', [
            'event_type' => 'review_request_queued', 'service' => 'parking', 'external_id' => 'P-10',
        ]);
    }

    public function test_already_sent_review_request_is_not_duplicated(): void
    {
        $this->seedReviewTemplates();

        $customer = ParkingCustomer::create([
            'source' => 'manual', 'name' => 'Maria Ionescu', 'normalized_phone' => '0733111222',
        ]);

        ParkingReservation::create([
            'source' => 'manual', 'external_id' => 'P-11', 'customer_id' => $customer->id,
            'status' => 'departed', 'check_out_at' => now()->subHours(25),
            'review_request_sent' => true,
        ]);

        $response = $this->postJson($this->endpoint(), [], $this->headers());

        $response->assertOk();
        $response->assertJson(['parking_queued' => 0, 'lodging_queued' => 0, 'skipped' => 0]);

        $this->assertSame(0, OutboundMessage::query()->where('service', 'parking')->count());
    }

    public function test_departed_parking_reservation_12h_ago_is_not_eligible(): void
    {
        $this->seedReviewTemplates();

        $customer = ParkingCustomer::create([
            'source' => 'manual', 'name' => 'Ana Dumitru', 'normalized_phone' => '0744111222',
        ]);

        ParkingReservation::create([
            'source' => 'manual', 'external_id' => 'P-12', 'customer_id' => $customer->id,
            'status' => 'departed', 'check_out_at' => now()->subHours(12),
        ]);

        $response = $this->postJson($this->endpoint(), [], $this->headers());

        $response->assertOk();
        $response->assertJson(['parking_queued' => 0, 'lodging_queued' => 0, 'skipped' => 0]);

        $this->assertSame(0, OutboundMessage::query()->where('service', 'parking')->count());
    }

    public function test_lodging_checked_out_yesterday_is_eligible_today_is_not(): void
    {
        $this->seedReviewTemplates();

        $property = LodgingProperty::create(['source' => 'manual', 'name' => 'Sky Center']);
        $room = Room::create(['source' => 'manual', 'property_id' => $property->id, 'name' => 'Camera 1']);

        $eligible = LodgingReservation::create([
            'source' => 'manual', 'external_id' => 'L-10', 'room_id' => $room->id,
            'guest_name' => 'Andrei Pop', 'normalized_phone' => '0755111222',
            'status' => 'checked_out', 'check_in' => now()->subDays(3)->toDateString(),
            'check_out' => now()->subDay()->toDateString(),
        ]);

        $notEligible = LodgingReservation::create([
            'source' => 'manual', 'external_id' => 'L-11', 'room_id' => $room->id,
            'guest_name' => 'Elena Vasile', 'normalized_phone' => '0766111222',
            'status' => 'checked_out', 'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->toDateString(),
        ]);

        $response = $this->postJson($this->endpoint(), [], $this->headers());

        $response->assertOk();
        $response->assertJson(['parking_queued' => 0, 'lodging_queued' => 1, 'skipped' => 0]);

        $this->assertDatabaseHas('outbound_messages', [
            'service' => 'lodging', 'reference_id' => $eligible->id, 'template_key' => 'review_request',
        ]);
        $this->assertSame(0, OutboundMessage::query()->where('service', 'lodging')->where('reference_id', $notEligible->id)->count());
    }

    public function test_missing_token_is_rejected(): void
    {
        $response = $this->postJson($this->endpoint(), []);

        $response->assertStatus(401);
    }
}
