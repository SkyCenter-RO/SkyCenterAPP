<?php

namespace Tests\Feature\Actions\Messaging;

use App\Actions\Messaging\RenderMessageTemplate;
use App\Models\MessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenderMessageTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_active_template_with_placeholders(): void
    {
        MessageTemplate::create([
            'source' => 'manual',
            'template_key' => 'confirmation',
            'service' => 'parking',
            'channel' => 'whatsapp',
            'locale' => 'ro',
            'label' => 'Confirmare parcare',
            'body' => 'Buna {{name}}, auto {{plate}}.',
            'is_active' => true,
        ]);

        $action = new RenderMessageTemplate();

        $result = $action->handle('parking', 'confirmation', [
            'name' => 'Ion',
            'plate' => 'B 123 ABC',
        ]);

        $this->assertSame([
            'channel' => 'whatsapp',
            'text' => 'Buna Ion, auto B 123 ABC.',
        ], $result);
    }

    public function test_returns_null_for_inactive_template(): void
    {
        MessageTemplate::create([
            'source' => 'manual',
            'template_key' => 'confirmation',
            'service' => 'parking',
            'channel' => 'whatsapp',
            'locale' => 'ro',
            'body' => 'Buna {{name}}.',
            'is_active' => false,
        ]);

        $action = new RenderMessageTemplate();

        $result = $action->handle('parking', 'confirmation', ['name' => 'Ion']);

        $this->assertNull($result);
    }

    public function test_returns_null_when_template_missing(): void
    {
        $action = new RenderMessageTemplate();

        $result = $action->handle('lodging', 'review_request', ['guest_name' => 'Maria']);

        $this->assertNull($result);
    }
}
