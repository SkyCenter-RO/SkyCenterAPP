# Sky Center — n8n Email Parsing → Reservations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an authenticated automation API (`/api/automation/parking-reservations`, `/api/automation/lodging-reservations`) that n8n calls after parsing confirmation emails, upserting `ParkingReservation`/`LodgingReservation` rows idempotently and logging every call to `automation_webhook_logs`/`automation_events`.

**Architecture:** A single bearer-token middleware (`automation.token`) protects a new `routes/api.php` group. Two single-action controllers receive parsed payloads, write a webhook log row first, delegate the upsert logic to two small Action classes (`UpsertParkingReservationFromWebhook`, `UpsertLodgingReservationFromWebhook`), then update the log row with the outcome and create an `AutomationEvent`. A small `PhoneNumber::normalize()` helper produces the `normalized_phone` value used to match/create `ParkingCustomer` records.

**Tech Stack:** Laravel 12, PHP 8.3, PostgreSQL 16, PHPUnit 11 (RefreshDatabase, `#[DataProvider]`). All commands run via `docker compose exec -T app ...`.

---

### Task 1: Configuration — automation token & default parking lot

**Files:**
- Modify: `config/skycenter.php`
- Modify: `.env.example`
- Modify: `phpunit.xml`

- [ ] **Step 1: Add config keys**

In `config/skycenter.php`, add two new keys:

```php
<?php

return [
    'bootstrap_admin_password' => env('ADMIN_BOOTSTRAP_PASSWORD', 'schimba-parola'),
    'automation_api_token' => env('AUTOMATION_API_TOKEN'),
    'default_parking_lot_id' => env('AUTOMATION_DEFAULT_PARKING_LOT_ID'),
];
```

- [ ] **Step 2: Document the new env vars in `.env.example`**

Append at the end of `.env.example`:

```
AUTOMATION_API_TOKEN=
AUTOMATION_DEFAULT_PARKING_LOT_ID=
```

- [ ] **Step 3: Add a fixed token for the test environment**

In `phpunit.xml`, inside the existing `<php>` block, add a new `<env>` line next to the other `<env>` entries (e.g. after `NIGHTWATCH_ENABLED`):

```xml
        <env name="AUTOMATION_API_TOKEN" value="test-automation-token"/>
```

- [ ] **Step 4: Verify config loads**

Run: `docker compose exec -T app php artisan config:clear`
Expected: `INFO  Configuration cache cleared successfully.`

- [ ] **Step 5: Commit**

```bash
git add config/skycenter.php .env.example phpunit.xml
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(automation): add config for automation API token and default parking lot

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Phone number normalization helper

**Files:**
- Create: `app/Support/PhoneNumber.php`
- Test: `tests/Unit/Support/PhoneNumberTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Support/PhoneNumberTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    #[DataProvider('phoneNumbers')]
    public function test_normalize_converts_to_local_format(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function phoneNumbers(): array
    {
        return [
            'plus40 with spaces' => ['+40 722 123 456', '0722123456'],
            '0040 prefix' => ['0040722123456', '0722123456'],
            'local with separators' => ['0722.123.456', '0722123456'],
            'already local' => ['0733111222', '0733111222'],
            'null input' => [null, null],
            'empty string' => ['', null],
        ];
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=PhoneNumberTest`
Expected: FAIL — `Class "App\Support\PhoneNumber" not found`

- [ ] **Step 3: Implement the helper**

Create `app/Support/PhoneNumber.php`:

```php
<?php

namespace App\Support;

class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0040')) {
            $digits = '0'.substr($digits, 4);
        } elseif (str_starts_with($digits, '40') && strlen($digits) === 11) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=PhoneNumberTest`
Expected: `Tests: 6 passed (6 assertions)`

- [ ] **Step 5: Commit**

```bash
git add app/Support/PhoneNumber.php tests/Unit/Support/PhoneNumberTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(automation): add phone number normalization helper

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Parking reservation webhook endpoint

**Files:**
- Create: `app/Http/Middleware/AuthenticateAutomationToken.php`
- Create: `routes/api.php`
- Modify: `bootstrap/app.php`
- Create: `app/Actions/Automation/UpsertParkingReservationFromWebhook.php`
- Create: `app/Http/Controllers/Api/Automation/ParkingReservationWebhookController.php`
- Test: `tests/Feature/Api/AutomationParkingReservationWebhookTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/AutomationParkingReservationWebhookTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=AutomationParkingReservationWebhookTest`
Expected: FAIL — 404 Not Found (route `/api/automation/parking-reservations` doesn't exist).

- [ ] **Step 3: Create the bearer token middleware**

Create `app/Http/Middleware/AuthenticateAutomationToken.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAutomationToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('skycenter.automation_api_token');
        $token = $request->bearerToken();

        if (! $expected || ! $token || ! hash_equals($expected, $token)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Create the upsert action**

Create `app/Actions/Automation/UpsertParkingReservationFromWebhook.php`:

```php
<?php

namespace App\Actions\Automation;

use App\Models\AutomationEvent;
use App\Models\AutomationWebhookLog;
use App\Models\ParkingCustomer;
use App\Models\ParkingReservation;
use App\Support\PhoneNumber;

class UpsertParkingReservationFromWebhook
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: string, http_status: int, response: array<string, mixed>, error?: string}
     */
    public function handle(array $payload, AutomationWebhookLog $log): array
    {
        if (($payload['event_type'] ?? null) === 'unparsed') {
            return $this->error('Email could not be parsed');
        }

        $externalId = $payload['external_id'] ?? null;
        $checkInAt = $payload['check_in_at'] ?? null;
        $checkOutAt = $payload['check_out_at'] ?? null;

        if (! $externalId || ! $checkInAt || ! $checkOutAt) {
            return $this->error('Missing required fields: external_id, check_in_at, check_out_at');
        }

        $customer = $this->upsertCustomer($payload);

        $existing = ParkingReservation::query()
            ->where('source', 'parcare_form')
            ->where('external_id', $externalId)
            ->first();

        $reservation = $existing ?? new ParkingReservation();
        $reservation->fill([
            'source' => 'parcare_form',
            'external_id' => $externalId,
            'customer_id' => $customer?->id ?? $reservation->customer_id,
            'lot_id' => $payload['lot_id'] ?? config('skycenter.default_parking_lot_id') ?? $reservation->lot_id,
            'status' => $existing?->status ?? 'pending_approval',
            'plate' => $payload['plate'] ?? $reservation->plate,
            'vehicle_type' => $payload['vehicle_type'] ?? $reservation->vehicle_type,
            'check_in_at' => $checkInAt,
            'check_out_at' => $checkOutAt,
            'adults' => $payload['adults'] ?? $reservation->adults,
            'children' => $payload['children'] ?? $reservation->children,
            'quoted_price' => $payload['quoted_price'] ?? $reservation->quoted_price,
            'currency' => $payload['currency'] ?? $reservation->currency ?? 'RON',
        ]);
        $reservation->save();

        AutomationEvent::create([
            'webhook_log_id' => $log->id,
            'event_type' => $existing ? 'reservation_updated' : 'reservation_created',
            'service' => 'parking',
            'external_id' => $externalId,
            'occurred_at' => now(),
            'status' => 'processed',
            'payload' => $payload,
        ]);

        return [
            'status' => 'processed',
            'http_status' => 200,
            'response' => ['id' => $reservation->id, 'status' => $reservation->status],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertCustomer(array $payload): ?ParkingCustomer
    {
        $normalizedPhone = PhoneNumber::normalize($payload['phone'] ?? null);

        if (! $normalizedPhone) {
            return null;
        }

        $customer = ParkingCustomer::query()->firstOrNew(['normalized_phone' => $normalizedPhone]);
        $customer->fill([
            'source' => 'parcare_form',
            'name' => $payload['name'] ?? $customer->name,
            'phone' => $payload['phone'] ?? $customer->phone,
            'normalized_phone' => $normalizedPhone,
            'email' => $payload['email'] ?? $customer->email,
        ]);
        $customer->save();

        return $customer;
    }

    /**
     * @return array{status: string, http_status: int, response: array<string, mixed>, error: string}
     */
    private function error(string $message): array
    {
        return [
            'status' => 'error',
            'http_status' => 422,
            'error' => $message,
            'response' => ['message' => $message],
        ];
    }
}
```

- [ ] **Step 5: Create the controller**

Create `app/Http/Controllers/Api/Automation/ParkingReservationWebhookController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Automation;

use App\Actions\Automation\UpsertParkingReservationFromWebhook;
use App\Http\Controllers\Controller;
use App\Models\AutomationWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParkingReservationWebhookController extends Controller
{
    public function __invoke(Request $request, UpsertParkingReservationFromWebhook $action): JsonResponse
    {
        $payload = $request->all();

        $log = AutomationWebhookLog::create([
            'endpoint' => 'parking-reservations',
            'service' => 'parking',
            'event_type' => $payload['event_type'] ?? 'reservation',
            'external_id' => $payload['external_id'] ?? null,
            'payload' => $payload,
            'status' => 'received',
            'http_status' => 0,
            'received_at' => now(),
        ]);

        $result = $action->handle($payload, $log);

        $log->update([
            'status' => $result['status'],
            'http_status' => $result['http_status'],
            'error_message' => $result['error'] ?? null,
            'response_body' => $result['response'],
            'processed_at' => now(),
        ]);

        return response()->json($result['response'], $result['http_status']);
    }
}
```

- [ ] **Step 6: Create the API routes file**

Create `routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\Automation\ParkingReservationWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('automation.token')->prefix('automation')->group(function (): void {
    Route::post('parking-reservations', ParkingReservationWebhookController::class);
});
```

- [ ] **Step 7: Register the API routes and middleware alias**

Modify `bootstrap/app.php`:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'automation.token' => \App\Http\Middleware\AuthenticateAutomationToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

- [ ] **Step 8: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=AutomationParkingReservationWebhookTest`
Expected: `Tests: 5 passed`

- [ ] **Step 9: Run full suite**

Run: `docker compose exec -T app php artisan test`
Expected: all tests pass (89 from Tasks 1-2 + 5 new = 94 passed).

- [ ] **Step 10: Commit**

```bash
git add app/Http/Middleware/AuthenticateAutomationToken.php routes/api.php bootstrap/app.php \
  app/Actions/Automation/UpsertParkingReservationFromWebhook.php \
  app/Http/Controllers/Api/Automation/ParkingReservationWebhookController.php \
  tests/Feature/Api/AutomationParkingReservationWebhookTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(automation): add parking reservation webhook endpoint

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Lodging reservation webhook endpoint

**Files:**
- Create: `app/Actions/Automation/UpsertLodgingReservationFromWebhook.php`
- Create: `app/Http/Controllers/Api/Automation/LodgingReservationWebhookController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/AutomationLodgingReservationWebhookTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/AutomationLodgingReservationWebhookTest.php`:

```php
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
        $this->assertSame('pending', $reservation->status);
        $this->assertSame('John Smith', $reservation->guest_name);

        $log = AutomationWebhookLog::query()->where('external_id', 'BDC-555')->first();
        $this->assertSame('processed', $log->status);

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
        $this->assertSame('error', $log->status);
        $this->assertSame(422, $log->http_status);
    }

    public function test_missing_token_is_rejected(): void
    {
        $response = $this->postJson($this->endpoint(), ['external_id' => 'AIR-999']);

        $response->assertStatus(401);
        $this->assertSame(0, AutomationWebhookLog::query()->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=AutomationLodgingReservationWebhookTest`
Expected: FAIL — 404 Not Found (route `/api/automation/lodging-reservations` doesn't exist).

- [ ] **Step 3: Create the upsert action**

Create `app/Actions/Automation/UpsertLodgingReservationFromWebhook.php`:

```php
<?php

namespace App\Actions\Automation;

use App\Models\AutomationEvent;
use App\Models\AutomationWebhookLog;
use App\Models\LodgingReservation;
use App\Support\PhoneNumber;

class UpsertLodgingReservationFromWebhook
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: string, http_status: int, response: array<string, mixed>, error?: string}
     */
    public function handle(array $payload, AutomationWebhookLog $log): array
    {
        if (($payload['event_type'] ?? null) === 'unparsed') {
            return $this->error('Email could not be parsed');
        }

        $source = $payload['source'] ?? null;
        $externalId = $payload['external_id'] ?? null;
        $checkIn = $payload['check_in'] ?? null;
        $checkOut = $payload['check_out'] ?? null;

        if (! $source || ! $externalId || ! $checkIn || ! $checkOut) {
            return $this->error('Missing required fields: source, external_id, check_in, check_out');
        }

        $existing = LodgingReservation::query()
            ->where('source', $source)
            ->where('external_id', $externalId)
            ->first();

        $reservation = $existing ?? new LodgingReservation();
        $reservation->fill([
            'source' => $source,
            'external_id' => $externalId,
            'guest_name' => $payload['guest_name'] ?? $reservation->guest_name,
            'phone' => $payload['phone'] ?? $reservation->phone,
            'normalized_phone' => PhoneNumber::normalize($payload['phone'] ?? null) ?? $reservation->normalized_phone,
            'email' => $payload['email'] ?? $reservation->email,
            'status' => $existing?->status ?? 'pending',
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'nights' => $payload['nights'] ?? $reservation->nights,
            'price' => $payload['price'] ?? $reservation->price,
            'currency' => $payload['currency'] ?? $reservation->currency ?? 'RON',
        ]);
        $reservation->save();

        AutomationEvent::create([
            'webhook_log_id' => $log->id,
            'event_type' => $existing ? 'reservation_updated' : 'reservation_created',
            'service' => 'lodging',
            'external_id' => $externalId,
            'occurred_at' => now(),
            'status' => 'processed',
            'payload' => $payload,
        ]);

        return [
            'status' => 'processed',
            'http_status' => 200,
            'response' => ['id' => $reservation->id, 'status' => $reservation->status],
        ];
    }

    /**
     * @return array{status: string, http_status: int, response: array<string, mixed>, error: string}
     */
    private function error(string $message): array
    {
        return [
            'status' => 'error',
            'http_status' => 422,
            'error' => $message,
            'response' => ['message' => $message],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Api/Automation/LodgingReservationWebhookController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Automation;

use App\Actions\Automation\UpsertLodgingReservationFromWebhook;
use App\Http\Controllers\Controller;
use App\Models\AutomationWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LodgingReservationWebhookController extends Controller
{
    public function __invoke(Request $request, UpsertLodgingReservationFromWebhook $action): JsonResponse
    {
        $payload = $request->all();

        $log = AutomationWebhookLog::create([
            'endpoint' => 'lodging-reservations',
            'service' => 'lodging',
            'event_type' => $payload['event_type'] ?? 'reservation',
            'external_id' => $payload['external_id'] ?? null,
            'payload' => $payload,
            'status' => 'received',
            'http_status' => 0,
            'received_at' => now(),
        ]);

        $result = $action->handle($payload, $log);

        $log->update([
            'status' => $result['status'],
            'http_status' => $result['http_status'],
            'error_message' => $result['error'] ?? null,
            'response_body' => $result['response'],
            'processed_at' => now(),
        ]);

        return response()->json($result['response'], $result['http_status']);
    }
}
```

- [ ] **Step 5: Register the route**

Modify `routes/api.php` to add the lodging route to the existing group:

```php
<?php

use App\Http\Controllers\Api\Automation\LodgingReservationWebhookController;
use App\Http\Controllers\Api\Automation\ParkingReservationWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('automation.token')->prefix('automation')->group(function (): void {
    Route::post('parking-reservations', ParkingReservationWebhookController::class);
    Route::post('lodging-reservations', LodgingReservationWebhookController::class);
});
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=AutomationLodgingReservationWebhookTest`
Expected: `Tests: 4 passed`

- [ ] **Step 7: Run full suite**

Run: `docker compose exec -T app php artisan test`
Expected: all tests pass (94 from Task 3 + 4 new = 98 passed).

- [ ] **Step 8: Commit**

```bash
git add app/Actions/Automation/UpsertLodgingReservationFromWebhook.php \
  app/Http/Controllers/Api/Automation/LodgingReservationWebhookController.php \
  routes/api.php tests/Feature/Api/AutomationLodgingReservationWebhookTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(automation): add lodging reservation webhook endpoint

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Full-stack verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `docker compose exec -T app php artisan test`
Expected: all tests pass, zero failures (98 total: 83 from subprojects #1/#2 + 6 PhoneNumber data sets + 5 parking webhook tests + 4 lodging webhook tests).

- [ ] **Step 2: Verify the API routes are registered**

Run: `docker compose exec -T app php artisan route:list --path=api`
Expected: shows `POST api/automation/parking-reservations` and `POST api/automation/lodging-reservations`, both with the `automation.token` middleware.

- [ ] **Step 3: Manual smoke test with curl (optional, if environment allows)**

Run (replace `test-automation-token` with the value configured in your local `.env`):

```bash
curl -s -X POST http://localhost:8080/api/automation/parking-reservations \
  -H "Authorization: Bearer test-automation-token" \
  -H "Content-Type: application/json" \
  -d '{"external_id":"SMOKE-1","name":"Test User","phone":"0722000000","check_in_at":"2026-07-01 10:00:00","check_out_at":"2026-07-02 10:00:00"}'
```

Expected: JSON response `{"id":...,"status":"pending_approval"}` with HTTP 200.

- [ ] **Step 4: Commit final verification**

```bash
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
test: verify n8n email parsing automation API

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)" --allow-empty
```

---

## Notes for n8n setup (outside this repo)

- Configure `AUTOMATION_API_TOKEN` in the app's `.env` (a long random string), and use the same value as a "Header Auth" credential in n8n (`Authorization: Bearer <token>`).
- Set `AUTOMATION_DEFAULT_PARKING_LOT_ID` in `.env` to the `id` of the default parking lot if the booking form doesn't always capture which lot.
- Each n8n workflow (parcare form, Booking.com, Airbnb) ends with an HTTP Request node POSTing the parsed JSON to the corresponding endpoint. If parsing fails, POST `{"event_type": "unparsed", "subject": ..., "raw_text": ...}` instead.
