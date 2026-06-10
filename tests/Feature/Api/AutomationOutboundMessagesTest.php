<?php

namespace Tests\Feature\Api;

use App\Models\AutomationEvent;
use App\Models\OutboundMessage;
use App\Models\ParkingCustomer;
use App\Models\ParkingReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationOutboundMessagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.config('skycenter.automation_api_token')];
    }

    public function test_lists_pending_messages_ordered_by_scheduled_at(): void
    {
        $older = OutboundMessage::create([
            'service' => 'parking', 'reference_id' => 1, 'channel' => 'whatsapp',
            'template_key' => 'confirmation',
            'payload' => ['text' => 'Mesaj 1', 'contact' => '0722111222', 'reservation_id' => 1],
            'scheduled_at' => now()->subMinutes(10), 'status' => 'pending',
        ]);

        $newer = OutboundMessage::create([
            'service' => 'lodging', 'reference_id' => 2, 'channel' => 'whatsapp',
            'template_key' => 'confirmation',
            'payload' => ['text' => 'Mesaj 2', 'contact' => '0733111222', 'reservation_id' => 2],
            'scheduled_at' => now(), 'status' => 'pending',
        ]);

        OutboundMessage::create([
            'service' => 'parking', 'reference_id' => 3, 'channel' => 'whatsapp',
            'template_key' => 'confirmation',
            'payload' => ['text' => 'Trimis deja', 'contact' => '0744111222', 'reservation_id' => 3],
            'scheduled_at' => now(), 'status' => 'sent',
        ]);

        $response = $this->getJson('/api/automation/outbound-messages?status=pending', $this->headers());

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $older->id);
        $response->assertJsonPath('data.0.text', 'Mesaj 1');
        $response->assertJsonPath('data.0.contact', '0722111222');
        $response->assertJsonPath('data.1.id', $newer->id);
    }

    public function test_callback_with_sent_status_marks_message_sent(): void
    {
        $customer = ParkingCustomer::create([
            'source' => 'manual', 'name' => 'Ion Pop', 'normalized_phone' => '0722111222',
        ]);
        $reservation = ParkingReservation::create([
            'source' => 'manual', 'external_id' => 'P-20', 'customer_id' => $customer->id,
            'status' => 'booked',
        ]);

        $message = OutboundMessage::create([
            'service' => 'parking', 'reference_id' => $reservation->id, 'channel' => 'whatsapp',
            'template_key' => 'confirmation',
            'payload' => ['text' => 'Mesaj', 'contact' => '0722111222', 'reservation_id' => $reservation->id],
            'scheduled_at' => now(), 'status' => 'pending',
        ]);

        $response = $this->postJson("/api/automation/outbound-messages/{$message->id}/callback", [
            'status' => 'sent',
        ], $this->headers());

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        $message->refresh();
        $this->assertSame('sent', $message->status);
        $this->assertNotNull($message->sent_at);

        $this->assertDatabaseHas('automation_events', [
            'event_type' => 'message_sent', 'service' => 'parking', 'external_id' => 'P-20',
        ]);
    }

    public function test_callback_with_failed_status_records_error_message(): void
    {
        $message = OutboundMessage::create([
            'service' => 'lodging', 'reference_id' => 999, 'channel' => 'whatsapp',
            'template_key' => 'confirmation',
            'payload' => ['text' => 'Mesaj', 'contact' => '0733111222', 'reservation_id' => 999],
            'scheduled_at' => now(), 'status' => 'pending',
        ]);

        $response = $this->postJson("/api/automation/outbound-messages/{$message->id}/callback", [
            'status' => 'failed', 'error_message' => 'WhatsApp API timeout',
        ], $this->headers());

        $response->assertOk();

        $message->refresh();
        $this->assertSame('failed', $message->status);
        $this->assertSame('WhatsApp API timeout', $message->payload['error_message']);

        $this->assertDatabaseHas('automation_events', [
            'event_type' => 'message_failed', 'service' => 'lodging',
        ]);
    }

    public function test_callback_on_already_processed_message_is_a_noop(): void
    {
        $message = OutboundMessage::create([
            'service' => 'parking', 'reference_id' => 1, 'channel' => 'whatsapp',
            'template_key' => 'confirmation',
            'payload' => ['text' => 'Mesaj', 'contact' => '0722111222', 'reservation_id' => 1],
            'scheduled_at' => now(), 'status' => 'sent', 'sent_at' => now(),
        ]);

        $eventsBefore = AutomationEvent::query()->count();

        $response = $this->postJson("/api/automation/outbound-messages/{$message->id}/callback", [
            'status' => 'sent',
        ], $this->headers());

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        $this->assertSame($eventsBefore, AutomationEvent::query()->count());
    }

    public function test_missing_token_is_rejected_on_all_endpoints(): void
    {
        $message = OutboundMessage::create([
            'service' => 'parking', 'reference_id' => 1, 'channel' => 'whatsapp',
            'template_key' => 'confirmation',
            'payload' => ['text' => 'Mesaj', 'contact' => '0722111222', 'reservation_id' => 1],
            'scheduled_at' => now(), 'status' => 'pending',
        ]);

        $this->getJson('/api/automation/outbound-messages')->assertStatus(401);
        $this->postJson("/api/automation/outbound-messages/{$message->id}/callback", ['status' => 'sent'])->assertStatus(401);
        $this->postJson('/api/automation/dispatch-review-requests')->assertStatus(401);
    }
}
