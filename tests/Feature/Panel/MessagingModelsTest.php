<?php

namespace Tests\Feature\Panel;

use App\Models\AutomationEvent;
use App\Models\AutomationWebhookLog;
use App\Models\MessageTemplate;
use App\Models\OutboundMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_templates_outbound_and_automation(): void
    {
        $template = MessageTemplate::create([
            'template_key' => 'parking_booked_confirm',
            'channel' => 'whatsapp',
            'body' => 'Bună ziua!',
            'is_active' => true,
        ]);
        $outbound = OutboundMessage::create([
            'service' => 'parking',
            'channel' => 'whatsapp',
            'scheduled_at' => now(),
            'status' => 'pending',
        ]);
        $log = AutomationWebhookLog::create([
            'endpoint' => '/webhook/n8n',
            'status' => 'accepted',
        ]);
        $event = AutomationEvent::create([
            'webhook_log_id' => $log->id,
            'event_type' => 'parking.confirmed',
            'status' => 'received',
        ]);

        $this->assertTrue($template->is_active);
        $this->assertSame('pending', $outbound->status);
        $this->assertSame($log->id, $event->webhookLog->id);
        $this->assertSame(1, $log->events()->count());
    }
}
