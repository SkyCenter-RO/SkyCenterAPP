# Sky Center — n8n Outbound Messaging (Confirmations + Review Requests) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Automatically queue customer messages (booking confirmations and "departed/checked-out + 24h" review requests) into `outbound_messages`, and expose 3 authenticated automation API endpoints (`POST /api/automation/dispatch-review-requests`, `GET /api/automation/outbound-messages`, `POST /api/automation/outbound-messages/{id}/callback`) for n8n to poll, send, and report back on.

**Architecture:** Two Eloquent observers (`ParkingReservationObserver`, `LodgingReservationObserver`) detect transitions into `booked`/`confirmed` and call `QueueConfirmationMessage`, which renders an active `MessageTemplate` via `RenderMessageTemplate` and creates a `pending` `OutboundMessage` + `automation_events` row. A scheduled-by-n8n endpoint (`DispatchReviewRequests`) scans `departed`/`checked_out` reservations 24h past check-out and queues `review_request` messages the same way. Two read/write endpoints let n8n list pending messages and report send results back, following the same `automation_webhook_logs`/`automation_events` audit pattern as flow #1.

**Tech Stack:** Laravel 12, PHP 8.3, PostgreSQL 16, PHPUnit 11 (RefreshDatabase). All commands run via `docker compose exec -T app ...`.

---

### Task 1: `review_request_sent` on `lodging_reservations`

**Files:**
- Modify: `tests/Feature/Schema/LodgingSchemaTest.php`
- Create: `database/migrations/2026_06_10_200000_add_review_request_sent_to_lodging_reservations.php`
- Modify: `app/Models/LodgingReservation.php`

- [ ] **Step 1: Write the failing schema assertion**

In `tests/Feature/Schema/LodgingSchemaTest.php`, modify `test_lodging_tables_exist()` so the `Schema::hasColumns()` array includes `'review_request_sent'`:

```php
    public function test_lodging_tables_exist(): void
    {
        foreach (['lodging_properties', 'rooms', 'lodging_reservations', 'lodging_sync_links'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing $table");
        }
        $this->assertTrue(Schema::hasColumns('lodging_reservations', [
            'guest_name', 'phone', 'email', 'status', 'check_in', 'check_out',
            'nights', 'price', 'direct_price', 'currency', 'source', 'review_request_sent',
        ]));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=LodgingSchemaTest`
Expected: FAIL — `review_request_sent` column missing from `lodging_reservations`.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_06_10_200000_add_review_request_sent_to_lodging_reservations.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lodging_reservations', function (Blueprint $table): void {
            $table->boolean('review_request_sent')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('lodging_reservations', function (Blueprint $table): void {
            $table->dropColumn('review_request_sent');
        });
    }
};
```

- [ ] **Step 4: Add the column to the model**

In `app/Models/LodgingReservation.php`, add `'review_request_sent'` to `$fillable` and a boolean cast:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LodgingReservation extends Model
{
    protected $fillable = [
        'source', 'external_id', 'room_id', 'guest_name', 'phone', 'normalized_phone', 'email',
        'status', 'review_request_sent', 'check_in', 'check_out', 'nights', 'price', 'direct_price',
        'currency', 'source_created_at', 'notes', 'metadata', 'created_by_id', 'updated_by_id',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'review_request_sent' => 'boolean',
        'source_created_at' => 'datetime',
        'nights' => 'integer',
        'price' => 'decimal:2',
        'direct_price' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=LodgingSchemaTest`
Expected: `Tests: 3 passed`

- [ ] **Step 6: Run full suite**

Run: `docker compose exec -T app php artisan test`
Expected: all 98 tests still pass.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_06_10_200000_add_review_request_sent_to_lodging_reservations.php \
  app/Models/LodgingReservation.php tests/Feature/Schema/LodgingSchemaTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(lodging): add review_request_sent flag to lodging reservations

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Message templates seed + `RenderMessageTemplate` action

**Files:**
- Create: `tests/Feature/Actions/Messaging/RenderMessageTemplateTest.php`
- Create: `app/Actions/Messaging/RenderMessageTemplate.php`
- Modify: `tests/Feature/Schema/SeederTest.php`
- Modify: `database/seeders/MessageTemplateSeeder.php`

- [ ] **Step 1: Write the failing test for `RenderMessageTemplate`**

Create `tests/Feature/Actions/Messaging/RenderMessageTemplateTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=RenderMessageTemplateTest`
Expected: FAIL — `App\Actions\Messaging\RenderMessageTemplate` not found.

- [ ] **Step 3: Implement `RenderMessageTemplate`**

Create `app/Actions/Messaging/RenderMessageTemplate.php`:

```php
<?php

namespace App\Actions\Messaging;

use App\Models\MessageTemplate;

class RenderMessageTemplate
{
    /**
     * @param  array<string, string>  $placeholders
     * @return array{channel: string, text: string}|null
     */
    public function handle(string $service, string $templateKey, array $placeholders, string $locale = 'ro'): ?array
    {
        $template = MessageTemplate::query()
            ->where('service', $service)
            ->where('template_key', $templateKey)
            ->where('locale', $locale)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return null;
        }

        $text = strtr($template->body, $this->wrapPlaceholders($placeholders));

        return ['channel' => $template->channel, 'text' => $text];
    }

    /**
     * @param  array<string, string>  $placeholders
     * @return array<string, string>
     */
    private function wrapPlaceholders(array $placeholders): array
    {
        $wrapped = [];

        foreach ($placeholders as $key => $value) {
            $wrapped['{{'.$key.'}}'] = $value;
        }

        return $wrapped;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=RenderMessageTemplateTest`
Expected: `Tests: 3 passed`

- [ ] **Step 5: Write the failing seeder test**

In `tests/Feature/Schema/SeederTest.php`, add a new test method at the end of the class:

```php
    public function test_seeds_four_message_templates(): void
    {
        $this->seed(\Database\Seeders\MessageTemplateSeeder::class);

        $this->assertSame(4, DB::table('message_templates')->count());

        foreach ([
            ['service' => 'parking', 'template_key' => 'confirmation'],
            ['service' => 'lodging', 'template_key' => 'confirmation'],
            ['service' => 'parking', 'template_key' => 'review_request'],
            ['service' => 'lodging', 'template_key' => 'review_request'],
        ] as $expected) {
            $this->assertDatabaseHas('message_templates', $expected + [
                'source' => 'manual',
                'locale' => 'ro',
                'channel' => 'whatsapp',
                'is_active' => true,
            ]);
        }
    }
```

- [ ] **Step 6: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=SeederTest`
Expected: FAIL — `message_templates` count is 2, not 4 (current seeder uses old `parking_booked_confirm`/`parking_departed_review` keys).

- [ ] **Step 7: Replace the seeder content**

Replace `database/seeders/MessageTemplateSeeder.php` with:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'template_key' => 'confirmation',
                'service' => 'parking',
                'channel' => 'whatsapp',
                'label' => 'Confirmare parcare',
                'body' => 'Bună {{name}}! Rezervarea ta de parcare e confirmată: {{check_in}} - {{check_out}}, auto {{plate}}. Te așteptăm la Sky Center!',
            ],
            [
                'template_key' => 'confirmation',
                'service' => 'lodging',
                'channel' => 'whatsapp',
                'label' => 'Confirmare cazare',
                'body' => 'Bună {{guest_name}}! Rezervarea ta la {{property}} ({{room}}) e confirmată: {{check_in}} - {{check_out}}. Te așteptăm!',
            ],
            [
                'template_key' => 'review_request',
                'service' => 'parking',
                'channel' => 'whatsapp',
                'label' => 'Cerere recenzie parcare',
                'body' => 'Bună {{name}}! Mulțumim că ai parcat la Sky Center. Ne-ar ajuta enorm o recenzie: [link recenzie]',
            ],
            [
                'template_key' => 'review_request',
                'service' => 'lodging',
                'channel' => 'whatsapp',
                'label' => 'Cerere recenzie cazare',
                'body' => 'Bună {{guest_name}}! Mulțumim că ai stat la {{property}}. Ne-ar ajuta enorm o recenzie: [link recenzie]',
            ],
        ];

        foreach ($templates as $t) {
            DB::table('message_templates')->insert(array_merge($t, [
                'source' => 'manual',
                'locale' => 'ro',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=SeederTest`
Expected: `Tests: 2 passed`

- [ ] **Step 9: Run full suite**

Run: `docker compose exec -T app php artisan test`
Expected: all tests pass (98 + 3 RenderMessageTemplate + 1 seeder = 102 passed).

- [ ] **Step 10: Commit**

```bash
git add app/Actions/Messaging/RenderMessageTemplate.php \
  tests/Feature/Actions/Messaging/RenderMessageTemplateTest.php \
  database/seeders/MessageTemplateSeeder.php tests/Feature/Schema/SeederTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(messaging): add RenderMessageTemplate action and seed confirmation/review templates

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Confirmation messages — `QueueConfirmationMessage` + observers

**Files:**
- Create: `tests/Feature/Messaging/ConfirmationMessageTest.php`
- Create: `app/Actions/Messaging/QueueConfirmationMessage.php`
- Create: `app/Observers/ParkingReservationObserver.php`
- Create: `app/Observers/LodgingReservationObserver.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Messaging/ConfirmationMessageTest.php`:

```php
<?php

namespace Tests\Feature\Messaging;

use App\Models\AutomationEvent;
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
        $this->assertSame('pending', $message->status);
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec -T app php artisan test --filter=ConfirmationMessageTest`
Expected: FAIL — no `OutboundMessage` rows are created (no observers registered yet).

- [ ] **Step 3: Implement `QueueConfirmationMessage`**

Create `app/Actions/Messaging/QueueConfirmationMessage.php`:

```php
<?php

namespace App\Actions\Messaging;

use App\Models\AutomationEvent;
use App\Models\LodgingReservation;
use App\Models\OutboundMessage;
use App\Models\ParkingReservation;

class QueueConfirmationMessage
{
    public function __construct(private RenderMessageTemplate $renderMessageTemplate)
    {
    }

    public function handleParking(ParkingReservation $reservation): void
    {
        $customer = $reservation->customer;

        $placeholders = [
            'name' => $customer?->name ?? '',
            'plate' => $reservation->plate ?? '',
            'check_in' => optional($reservation->check_in_at)->format('d.m.Y H:i') ?? '',
            'check_out' => optional($reservation->check_out_at)->format('d.m.Y H:i') ?? '',
        ];

        $this->queue(
            service: 'parking',
            reservationId: $reservation->id,
            externalId: $reservation->external_id,
            placeholders: $placeholders,
            phone: $customer?->normalized_phone,
            email: $customer?->email,
        );
    }

    public function handleLodging(LodgingReservation $reservation): void
    {
        $room = $reservation->room;
        $property = $room?->property;

        $placeholders = [
            'guest_name' => $reservation->guest_name ?? '',
            'property' => $property?->name ?? '',
            'room' => $room?->name ?? '',
            'check_in' => optional($reservation->check_in)->format('d.m.Y') ?? '',
            'check_out' => optional($reservation->check_out)->format('d.m.Y') ?? '',
        ];

        $this->queue(
            service: 'lodging',
            reservationId: $reservation->id,
            externalId: $reservation->external_id,
            placeholders: $placeholders,
            phone: $reservation->normalized_phone,
            email: $reservation->email,
        );
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    private function queue(
        string $service,
        int $reservationId,
        ?string $externalId,
        array $placeholders,
        ?string $phone,
        ?string $email,
    ): void {
        $rendered = $this->renderMessageTemplate->handle($service, 'confirmation', $placeholders);

        if ($rendered === null) {
            AutomationEvent::create([
                'webhook_log_id' => null,
                'event_type' => 'message_template_missing',
                'service' => $service,
                'external_id' => $externalId,
                'occurred_at' => now(),
                'status' => 'skipped',
                'payload' => ['reservation_id' => $reservationId, 'template_key' => 'confirmation'],
            ]);

            return;
        }

        $contact = $rendered['channel'] === 'email' ? $email : $phone;

        if (! $contact) {
            AutomationEvent::create([
                'webhook_log_id' => null,
                'event_type' => 'message_contact_missing',
                'service' => $service,
                'external_id' => $externalId,
                'occurred_at' => now(),
                'status' => 'skipped',
                'payload' => ['reservation_id' => $reservationId, 'channel' => $rendered['channel']],
            ]);

            return;
        }

        OutboundMessage::create([
            'service' => $service,
            'reference_id' => $reservationId,
            'channel' => $rendered['channel'],
            'template_key' => 'confirmation',
            'payload' => ['text' => $rendered['text'], 'contact' => $contact, 'reservation_id' => $reservationId],
            'scheduled_at' => now(),
            'status' => 'pending',
        ]);

        AutomationEvent::create([
            'webhook_log_id' => null,
            'event_type' => 'confirmation_queued',
            'service' => $service,
            'external_id' => $externalId,
            'occurred_at' => now(),
            'status' => 'processed',
            'payload' => ['reservation_id' => $reservationId, 'channel' => $rendered['channel']],
        ]);
    }
}
```

- [ ] **Step 4: Implement the observers**

Create `app/Observers/ParkingReservationObserver.php`:

```php
<?php

namespace App\Observers;

use App\Actions\Messaging\QueueConfirmationMessage;
use App\Models\ParkingReservation;

class ParkingReservationObserver
{
    public function __construct(private QueueConfirmationMessage $queueConfirmation)
    {
    }

    public function created(ParkingReservation $reservation): void
    {
        if ($reservation->status === 'booked') {
            $this->queueConfirmation->handleParking($reservation);
        }
    }

    public function updated(ParkingReservation $reservation): void
    {
        if ($reservation->wasChanged('status')
            && $reservation->status === 'booked'
            && $reservation->getOriginal('status') !== 'booked') {
            $this->queueConfirmation->handleParking($reservation);
        }
    }
}
```

Create `app/Observers/LodgingReservationObserver.php`:

```php
<?php

namespace App\Observers;

use App\Actions\Messaging\QueueConfirmationMessage;
use App\Models\LodgingReservation;

class LodgingReservationObserver
{
    public function __construct(private QueueConfirmationMessage $queueConfirmation)
    {
    }

    public function created(LodgingReservation $reservation): void
    {
        if ($reservation->status === 'confirmed') {
            $this->queueConfirmation->handleLodging($reservation);
        }
    }

    public function updated(LodgingReservation $reservation): void
    {
        if ($reservation->wasChanged('status')
            && $reservation->status === 'confirmed'
            && $reservation->getOriginal('status') !== 'confirmed') {
            $this->queueConfirmation->handleLodging($reservation);
        }
    }
}
```

- [ ] **Step 5: Register the observers**

Modify `app/Providers/AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Models\LodgingReservation;
use App\Models\ParkingReservation;
use App\Observers\LodgingReservationObserver;
use App\Observers\ParkingReservationObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        ParkingReservation::observe(ParkingReservationObserver::class);
        LodgingReservation::observe(LodgingReservationObserver::class);
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `docker compose exec -T app php artisan test --filter=ConfirmationMessageTest`
Expected: `Tests: 7 passed`

- [ ] **Step 7: Run full suite**

Run: `docker compose exec -T app php artisan test`
Expected: all tests pass (102 + 7 = 109 passed).

- [ ] **Step 8: Commit**

```bash
git add app/Actions/Messaging/QueueConfirmationMessage.php \
  app/Observers/ParkingReservationObserver.php app/Observers/LodgingReservationObserver.php \
  app/Providers/AppServiceProvider.php tests/Feature/Messaging/ConfirmationMessageTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(messaging): queue booking confirmations via reservation observers

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Dispatch review requests (`POST /api/automation/dispatch-review-requests`)

**Files:**
- Create: `tests/Feature/Api/AutomationDispatchReviewRequestsTest.php`
- Create: `app/Actions/Automation/DispatchReviewRequests.php`
- Create: `app/Http/Controllers/Api/Automation/DispatchReviewRequestsController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/AutomationDispatchReviewRequestsTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\AutomationEvent;
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec -T app php artisan test --filter=AutomationDispatchReviewRequestsTest`
Expected: FAIL — `404 Not Found` (route doesn't exist yet).

- [ ] **Step 3: Implement `DispatchReviewRequests`**

Create `app/Actions/Automation/DispatchReviewRequests.php`:

```php
<?php

namespace App\Actions\Automation;

use App\Actions\Messaging\RenderMessageTemplate;
use App\Models\AutomationEvent;
use App\Models\LodgingReservation;
use App\Models\OutboundMessage;
use App\Models\ParkingReservation;

class DispatchReviewRequests
{
    public function __construct(private RenderMessageTemplate $renderMessageTemplate)
    {
    }

    /**
     * @return array{parking_queued: int, lodging_queued: int, skipped: int}
     */
    public function handle(): array
    {
        $parkingQueued = 0;
        $lodgingQueued = 0;
        $skipped = 0;

        $parkingReservations = ParkingReservation::query()
            ->where('status', 'departed')
            ->where('check_out_at', '<=', now()->subHours(24))
            ->where('review_request_sent', false)
            ->get();

        foreach ($parkingReservations as $reservation) {
            $this->queueParkingReviewRequest($reservation) ? $parkingQueued++ : $skipped++;
        }

        $lodgingReservations = LodgingReservation::query()
            ->where('status', 'checked_out')
            ->where('check_out', '<=', now()->subDay()->toDateString())
            ->where('review_request_sent', false)
            ->get();

        foreach ($lodgingReservations as $reservation) {
            $this->queueLodgingReviewRequest($reservation) ? $lodgingQueued++ : $skipped++;
        }

        return [
            'parking_queued' => $parkingQueued,
            'lodging_queued' => $lodgingQueued,
            'skipped' => $skipped,
        ];
    }

    private function queueParkingReviewRequest(ParkingReservation $reservation): bool
    {
        $customer = $reservation->customer;

        $rendered = $this->renderMessageTemplate->handle('parking', 'review_request', [
            'name' => $customer?->name ?? '',
        ]);

        return $this->queue($reservation, 'parking', $rendered, $customer?->normalized_phone, $customer?->email);
    }

    private function queueLodgingReviewRequest(LodgingReservation $reservation): bool
    {
        $property = $reservation->room?->property;

        $rendered = $this->renderMessageTemplate->handle('lodging', 'review_request', [
            'guest_name' => $reservation->guest_name ?? '',
            'property' => $property?->name ?? '',
        ]);

        return $this->queue($reservation, 'lodging', $rendered, $reservation->normalized_phone, $reservation->email);
    }

    /**
     * @param  array{channel: string, text: string}|null  $rendered
     */
    private function queue(
        ParkingReservation|LodgingReservation $reservation,
        string $service,
        ?array $rendered,
        ?string $phone,
        ?string $email,
    ): bool {
        if ($rendered === null) {
            AutomationEvent::create([
                'webhook_log_id' => null,
                'event_type' => 'message_template_missing',
                'service' => $service,
                'external_id' => $reservation->external_id,
                'occurred_at' => now(),
                'status' => 'skipped',
                'payload' => ['reservation_id' => $reservation->id, 'template_key' => 'review_request'],
            ]);

            return false;
        }

        $contact = $rendered['channel'] === 'email' ? $email : $phone;

        if (! $contact) {
            AutomationEvent::create([
                'webhook_log_id' => null,
                'event_type' => 'message_contact_missing',
                'service' => $service,
                'external_id' => $reservation->external_id,
                'occurred_at' => now(),
                'status' => 'skipped',
                'payload' => ['reservation_id' => $reservation->id, 'channel' => $rendered['channel']],
            ]);

            return false;
        }

        OutboundMessage::create([
            'service' => $service,
            'reference_id' => $reservation->id,
            'channel' => $rendered['channel'],
            'template_key' => 'review_request',
            'payload' => ['text' => $rendered['text'], 'contact' => $contact, 'reservation_id' => $reservation->id],
            'scheduled_at' => now(),
            'status' => 'pending',
        ]);

        $reservation->update(['review_request_sent' => true]);

        AutomationEvent::create([
            'webhook_log_id' => null,
            'event_type' => 'review_request_queued',
            'service' => $service,
            'external_id' => $reservation->external_id,
            'occurred_at' => now(),
            'status' => 'processed',
            'payload' => ['reservation_id' => $reservation->id, 'channel' => $rendered['channel']],
        ]);

        return true;
    }
}
```

- [ ] **Step 4: Implement the controller**

Create `app/Http/Controllers/Api/Automation/DispatchReviewRequestsController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Automation;

use App\Actions\Automation\DispatchReviewRequests;
use App\Http\Controllers\Controller;
use App\Models\AutomationWebhookLog;
use Illuminate\Http\JsonResponse;

class DispatchReviewRequestsController extends Controller
{
    public function __invoke(DispatchReviewRequests $action): JsonResponse
    {
        $result = $action->handle();

        AutomationWebhookLog::create([
            'endpoint' => 'dispatch-review-requests',
            'event_type' => 'review_request_dispatch',
            'status' => 'processed',
            'http_status' => 200,
            'response_body' => $result,
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        return response()->json($result);
    }
}
```

- [ ] **Step 5: Register the route**

Modify `routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\Automation\DispatchReviewRequestsController;
use App\Http\Controllers\Api\Automation\LodgingReservationWebhookController;
use App\Http\Controllers\Api\Automation\ParkingReservationWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('automation.token')->prefix('automation')->group(function (): void {
    Route::post('parking-reservations', ParkingReservationWebhookController::class);
    Route::post('lodging-reservations', LodgingReservationWebhookController::class);
    Route::post('dispatch-review-requests', DispatchReviewRequestsController::class);
});
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `docker compose exec -T app php artisan test --filter=AutomationDispatchReviewRequestsTest`
Expected: `Tests: 5 passed`

- [ ] **Step 7: Run full suite**

Run: `docker compose exec -T app php artisan test`
Expected: all tests pass (109 + 5 = 114 passed).

- [ ] **Step 8: Commit**

```bash
git add app/Actions/Automation/DispatchReviewRequests.php \
  app/Http/Controllers/Api/Automation/DispatchReviewRequestsController.php \
  routes/api.php tests/Feature/Api/AutomationDispatchReviewRequestsTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(automation): add dispatch-review-requests endpoint for departed+24h follow-ups

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Outbound messages list + callback API

**Files:**
- Create: `tests/Feature/Api/AutomationOutboundMessagesTest.php`
- Create: `app/Http/Controllers/Api/Automation/OutboundMessagesController.php`
- Create: `app/Http/Controllers/Api/Automation/OutboundMessageCallbackController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/AutomationOutboundMessagesTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec -T app php artisan test --filter=AutomationOutboundMessagesTest`
Expected: FAIL — `404 Not Found` (routes don't exist yet).

- [ ] **Step 3: Implement the list controller**

Create `app/Http/Controllers/Api/Automation/OutboundMessagesController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Automation;

use App\Http\Controllers\Controller;
use App\Models\OutboundMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutboundMessagesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');

        $messages = OutboundMessage::query()
            ->where('status', $status)
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        $data = $messages->map(fn (OutboundMessage $message): array => [
            'id' => $message->id,
            'service' => $message->service,
            'reference_id' => $message->reference_id,
            'channel' => $message->channel,
            'template_key' => $message->template_key,
            'text' => $message->payload['text'] ?? null,
            'contact' => $message->payload['contact'] ?? null,
            'scheduled_at' => $message->scheduled_at?->toAtomString(),
        ]);

        return response()->json(['data' => $data]);
    }
}
```

- [ ] **Step 4: Implement the callback controller**

Create `app/Http/Controllers/Api/Automation/OutboundMessageCallbackController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Automation;

use App\Http\Controllers\Controller;
use App\Models\AutomationEvent;
use App\Models\AutomationWebhookLog;
use App\Models\LodgingReservation;
use App\Models\OutboundMessage;
use App\Models\ParkingReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutboundMessageCallbackController extends Controller
{
    public function __invoke(Request $request, OutboundMessage $outboundMessage): JsonResponse
    {
        if ($outboundMessage->status !== 'pending') {
            return response()->json(['status' => 'ok']);
        }

        $payload = $request->all();
        $status = $payload['status'] ?? null;

        if ($status === 'sent') {
            $outboundMessage->status = 'sent';
            $outboundMessage->sent_at = now();
        } else {
            $outboundMessage->status = 'failed';
            $outboundMessage->payload = array_merge($outboundMessage->payload ?? [], [
                'error_message' => $payload['error_message'] ?? null,
            ]);
        }
        $outboundMessage->save();

        $externalId = $this->resolveExternalId($outboundMessage);
        $eventType = $status === 'sent' ? 'message_sent' : 'message_failed';

        $log = AutomationWebhookLog::create([
            'endpoint' => 'outbound-messages-callback',
            'event_type' => $eventType,
            'service' => $outboundMessage->service,
            'external_id' => $externalId,
            'payload' => $payload,
            'status' => 'processed',
            'http_status' => 200,
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        AutomationEvent::create([
            'webhook_log_id' => $log->id,
            'event_type' => $eventType,
            'service' => $outboundMessage->service,
            'external_id' => $externalId,
            'occurred_at' => now(),
            'status' => 'processed',
            'payload' => $payload,
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function resolveExternalId(OutboundMessage $outboundMessage): ?string
    {
        return match ($outboundMessage->service) {
            'parking' => ParkingReservation::find($outboundMessage->reference_id)?->external_id,
            'lodging' => LodgingReservation::find($outboundMessage->reference_id)?->external_id,
            default => null,
        };
    }
}
```

- [ ] **Step 5: Register the routes**

Modify `routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\Automation\DispatchReviewRequestsController;
use App\Http\Controllers\Api\Automation\LodgingReservationWebhookController;
use App\Http\Controllers\Api\Automation\OutboundMessageCallbackController;
use App\Http\Controllers\Api\Automation\OutboundMessagesController;
use App\Http\Controllers\Api\Automation\ParkingReservationWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('automation.token')->prefix('automation')->group(function (): void {
    Route::post('parking-reservations', ParkingReservationWebhookController::class);
    Route::post('lodging-reservations', LodgingReservationWebhookController::class);
    Route::post('dispatch-review-requests', DispatchReviewRequestsController::class);
    Route::get('outbound-messages', OutboundMessagesController::class);
    Route::post('outbound-messages/{outboundMessage}/callback', OutboundMessageCallbackController::class);
});
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `docker compose exec -T app php artisan test --filter=AutomationOutboundMessagesTest`
Expected: `Tests: 5 passed`

- [ ] **Step 7: Run full suite**

Run: `docker compose exec -T app php artisan test`
Expected: all tests pass (114 + 5 = 119 passed).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Api/Automation/OutboundMessagesController.php \
  app/Http/Controllers/Api/Automation/OutboundMessageCallbackController.php \
  routes/api.php tests/Feature/Api/AutomationOutboundMessagesTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(automation): add outbound messages list and callback endpoints

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Full-stack verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `docker compose exec -T app php artisan test`
Expected: all tests pass, zero failures (119 total).

- [ ] **Step 2: Verify the API routes are registered**

Run: `docker compose exec -T app php artisan route:list --path=api`
Expected: shows `POST api/automation/parking-reservations`, `POST api/automation/lodging-reservations`, `POST api/automation/dispatch-review-requests`, `GET api/automation/outbound-messages`, `POST api/automation/outbound-messages/{outboundMessage}/callback`, all with the `automation.token` middleware.

- [ ] **Step 3: Manual smoke test with curl (optional, if environment allows)**

Run (replace `test-automation-token` with the value configured in your local `.env`):

```bash
curl -s -X POST http://localhost:8080/api/automation/dispatch-review-requests \
  -H "Authorization: Bearer test-automation-token" \
  -H "Content-Type: application/json" -d '{}'

curl -s "http://localhost:8080/api/automation/outbound-messages?status=pending" \
  -H "Authorization: Bearer test-automation-token"
```

Expected: first call returns `{"parking_queued":...,"lodging_queued":...,"skipped":...}`; second returns `{"data":[...]}`.

- [ ] **Step 4: Commit final verification**

```bash
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
test: verify n8n outbound messaging automation API

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)" --allow-empty
```

---

## Notes for n8n setup (outside this repo)

- Schedule trigger (e.g. every 15 minutes) calls, in order: `POST /api/automation/dispatch-review-requests`, then `GET /api/automation/outbound-messages?status=pending`.
- For each message returned, send `text` to `contact` on the given `channel` (whatsapp/telegram/viber/sms/email). For `telegram`/`viber`, n8n maps the normalized phone number to the corresponding chat ID.
- After sending, call `POST /api/automation/outbound-messages/{id}/callback` with `{"status": "sent"}` or `{"status": "failed", "error_message": "..."}`.
- All 5 endpoints use the existing `Authorization: Bearer <AUTOMATION_API_TOKEN>` header.
- Operators can edit channel/text/locale for all 4 seeded templates directly from the **Mesagerie** Filament resource.
