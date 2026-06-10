# Telegram Budget Bot Implementation Plan

> **For Antigravity:** REQUIRED WORKFLOW: Use `.agent/workflows/execute-plan.md` to execute this plan in single-flow mode.

**Goal:** Build a guided Telegram bot wizard (income + expense groups) that saves structured budget transactions into `budget_transactions` via two Laravel API endpoints, managed through a `telegram_sessions` state machine.

**Architecture:** Two thin n8n workflows relay Telegram events (messages + callback queries) to Laravel via `POST /api/automation/telegram/{income|expense}`. Laravel owns all state in a new `telegram_sessions` table and returns `{action, text, keyboard}` for n8n to relay back to Telegram. On wizard completion, `budget_raw_messages` and `budget_transactions` records are created.

**Tech Stack:** Laravel 12, PHP 8.3, PostgreSQL 16, PHPUnit 11, Telegram Bot API (inline keyboards), n8n (documented but not coded — configured manually).

**Spec:** `docs/superpowers/specs/2026-06-10-skycenter-telegram-budget-bot-design.md`

---

### Task 1: Migration — `telegram_sessions` table + extend `budget_categories` seed

**Files:**
- Create: `database/migrations/2026_06_10_210000_create_telegram_sessions_table.php`
- Modify: `database/seeders/BudgetCategorySeeder.php`
- Test: `tests/Feature/Schema/CommonSchemaTest.php` (extend)

---

- [ ] **Step 1: Add failing test for `telegram_sessions` schema**

Append to `tests/Feature/Schema/CommonSchemaTest.php`:

```php
public function test_telegram_sessions_table_exists(): void
{
    $this->assertTrue(Schema::hasColumns('telegram_sessions', [
        'chat_id', 'user_id', 'username', 'group_type',
        'state', 'data', 'wizard_message_id', 'expires_at',
    ]));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=test_telegram_sessions_table_exists`
Expected: FAIL — `telegram_sessions` does not exist.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_06_10_210000_create_telegram_sessions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('chat_id', 64)->index();
            $table->string('user_id', 64)->nullable();
            $table->string('username', 128)->nullable();
            $table->string('group_type', 16);
            $table->string('state', 64);
            $table->jsonb('data')->nullable();
            $table->unsignedInteger('wizard_message_id')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->unique(['chat_id', 'user_id'], 'telegram_sessions_chat_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_sessions');
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=test_telegram_sessions_table_exists`
Expected: PASS.

- [ ] **Step 5: Expand `BudgetCategorySeeder`**

Replace the contents of `database/seeders/BudgetCategorySeeder.php` with:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BudgetCategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('budget_categories')->truncate();

        $categories = [
            // General expenses (shown in expense wizard)
            ['service' => 'general', 'name' => 'Kaufland',        'emoji' => '🛒'],
            ['service' => 'general', 'name' => 'Curățenie',       'emoji' => '🧹'],
            ['service' => 'general', 'name' => 'Spălat mașini',   'emoji' => '🚗'],
            ['service' => 'general', 'name' => 'Instalatori',     'emoji' => '🔧'],
            ['service' => 'general', 'name' => 'Grădinar',        'emoji' => '🌿'],
            ['service' => 'general', 'name' => 'Contabil',        'emoji' => '📊'],
            ['service' => 'general', 'name' => 'Combustibil',     'emoji' => '⛽'],
            ['service' => 'general', 'name' => 'Piese auto',      'emoji' => '🔩'],
            ['service' => 'general', 'name' => 'Rovinietă',       'emoji' => '📋'],
            ['service' => 'general', 'name' => 'Salarii',         'emoji' => '👤'],
            // Hotel-specific expenses
            ['service' => 'hotel',   'name' => 'Apă',             'emoji' => '💧'],
            ['service' => 'hotel',   'name' => 'Lumină',          'emoji' => '💡'],
            ['service' => 'hotel',   'name' => 'Gaz',             'emoji' => '🔥'],
            ['service' => 'hotel',   'name' => 'Inventar',        'emoji' => '📦'],
        ];

        foreach ($categories as $cat) {
            DB::table('budget_categories')->insert([
                'service'    => $cat['service'],
                'name'       => $cat['name'],
                'kind'       => 'expense',
                'frequency'  => 'once',
                'currency'   => 'RON',
                'is_active'  => true,
                'metadata'   => json_encode(['emoji' => $cat['emoji']]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
```

- [ ] **Step 6: Run full migrate:fresh --seed to verify no regressions**

Run: `docker compose exec -T app php artisan migrate:fresh --seed --force`
Expected: completes without errors.

- [ ] **Step 7: Run full test suite**

Run: `docker compose exec -T app php artisan test`
Expected: all existing tests pass.

- [ ] **Step 8: Commit**

```
git add database/migrations/2026_06_10_210000_create_telegram_sessions_table.php
git add database/seeders/BudgetCategorySeeder.php
git add tests/Feature/Schema/CommonSchemaTest.php
git commit -m "feat(db): add telegram_sessions table and expand budget categories seed"
```

---

### Task 2: `TelegramSession` model + `BudgetCategory` model update

**Files:**
- Create: `app/Models/TelegramSession.php`
- Modify: `app/Models/BudgetCategory.php`

---

- [ ] **Step 1: Create `TelegramSession` model**

Create `app/Models/TelegramSession.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramSession extends Model
{
    protected $fillable = [
        'chat_id',
        'user_id',
        'username',
        'group_type',
        'state',
        'data',
        'wizard_message_id',
        'expires_at',
    ];

    protected $casts = [
        'data'       => 'array',
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public static function findOrCreate(string $chatId, string $userId, string $groupType): static
    {
        return static::firstOrCreate(
            ['chat_id' => $chatId, 'user_id' => $userId],
            [
                'group_type' => $groupType,
                'state'      => 'start',
                'data'       => [],
                'expires_at' => now()->addMinutes(30),
            ]
        );
    }

    public function touch(array $merge = []): static
    {
        $this->fill(array_merge(['expires_at' => now()->addMinutes(30)], $merge));
        $this->save();
        return $this;
    }

    public function mergeData(array $data): static
    {
        $this->data = array_merge($this->data ?? [], $data);
        return $this;
    }
}
```

- [ ] **Step 2: Ensure `BudgetCategory` exposes `emoji` from metadata**

Check `app/Models/BudgetCategory.php` — add an accessor if it doesn't have one:

```php
public function getEmojiAttribute(): string
{
    return $this->metadata['emoji'] ?? '📌';
}
```

Also ensure `metadata` is in `$casts`:
```php
'metadata' => 'array',
```

- [ ] **Step 3: Commit**

```
git add app/Models/TelegramSession.php app/Models/BudgetCategory.php
git commit -m "feat(models): add TelegramSession model and BudgetCategory emoji accessor"
```

---

### Task 3: Income wizard — `ProcessIncomeTelegramUpdate` action

**Files:**
- Create: `app/Actions/Telegram/ProcessIncomeTelegramUpdate.php`
- Test: `tests/Feature/Telegram/IncomeTelegramWizardTest.php`

This action receives the normalized Telegram update payload, manages `TelegramSession` state,
and returns `['action' => 'send'|'edit'|'none', 'chat_id', 'message_id', 'text', 'keyboard']`.

---

- [ ] **Step 1: Write failing tests for income wizard**

Create `tests/Feature/Telegram/IncomeTelegramWizardTest.php`:

```php
<?php

namespace Tests\Feature\Telegram;

use App\Models\BudgetTransaction;
use App\Models\TelegramSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeTelegramWizardTest extends TestCase
{
    use RefreshDatabase;

    private function incomePost(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . config('automation.token'),
        ])->postJson('/api/automation/telegram/income', $payload);
    }

    private function msg(string $text, string $chatId = '-100111', string $userId = '1001'): array
    {
        return [
            'update_type' => 'message',
            'chat_id'     => $chatId,
            'user_id'     => $userId,
            'username'    => 'TestUser',
            'message_id'  => rand(1, 9999),
            'text'        => $text,
            'callback_query_id' => null,
            'callback_data'     => null,
        ];
    }

    private function cb(string $data, string $chatId = '-100111', string $userId = '1001'): array
    {
        return [
            'update_type'       => 'callback_query',
            'chat_id'           => $chatId,
            'user_id'           => $userId,
            'username'          => 'TestUser',
            'message_id'        => 50,
            'text'              => null,
            'callback_query_id' => 'cbq123',
            'callback_data'     => $data,
        ];
    }

    public function test_fresh_message_returns_service_selection(): void
    {
        $res = $this->incomePost($this->msg('hello'));
        $res->assertOk();
        $data = $res->json();
        $this->assertEquals('send', $data['action']);
        $this->assertStringContainsString('încasare', strtolower($data['text']));
        $this->assertNotNull($data['keyboard']);
        $this->assertEquals('selecting_service', TelegramSession::first()->state);
    }

    public function test_parking_service_selection_prompts_plate(): void
    {
        $this->incomePost($this->msg('start'));
        $res = $this->incomePost($this->cb('service:parking'));
        $res->assertOk();
        $this->assertStringContainsString('înmatriculare', strtolower($res->json('text')));
        $this->assertEquals('waiting_plate', TelegramSession::first()->state);
    }

    public function test_plate_input_prompts_amount(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:parking'));
        $res = $this->incomePost($this->msg('BX 1234 AB'));
        $res->assertOk();
        $this->assertStringContainsString('sumă', strtolower($res->json('text')));
        $this->assertEquals('waiting_amount', TelegramSession::first()->state);
        $this->assertEquals('BX 1234 AB', TelegramSession::first()->data['plate']);
    }

    public function test_invalid_amount_returns_error_and_keeps_state(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:parking'));
        $this->incomePost($this->msg('BX 1234 AB'));
        $res = $this->incomePost($this->msg('abc'));
        $res->assertOk();
        $this->assertStringContainsString('invalid', strtolower($res->json('text')));
        $this->assertEquals('waiting_amount', TelegramSession::first()->state);
    }

    public function test_valid_amount_prompts_payment_method(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:parking'));
        $this->incomePost($this->msg('BX 1234 AB'));
        $res = $this->incomePost($this->msg('250'));
        $res->assertOk();
        $this->assertStringContainsString('plată', strtolower($res->json('text')));
        $this->assertEquals('selecting_payment', TelegramSession::first()->state);
        $this->assertEquals(250.0, TelegramSession::first()->data['amount']);
    }

    public function test_payment_selection_saves_transaction_and_clears_session(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:parking'));
        $this->incomePost($this->msg('BX 1234 AB'));
        $this->incomePost($this->msg('250'));
        $res = $this->incomePost($this->cb('payment:card'));
        $res->assertOk();
        $this->assertStringContainsString('salvat', strtolower($res->json('text')));
        $this->assertDatabaseMissing('telegram_sessions', ['chat_id' => '-100111']);
        $this->assertDatabaseHas('budget_transactions', [
            'type'    => 'income',
            'service' => 'parking',
            'amount'  => 250.00,
            'currency' => 'RON',
        ]);
    }

    public function test_hotel_flow_property_then_rooms(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:hotel'));
        $res = $this->incomePost($this->cb('property:skycenter'));
        $res->assertOk();
        $this->assertStringContainsString('camera', strtolower($res->json('text')));
        $this->assertEquals('selecting_rooms', TelegramSession::first()->state);
    }

    public function test_hotel_room_toggle_and_confirm(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:hotel'));
        $this->incomePost($this->cb('property:skycenter'));
        $this->incomePost($this->cb('room:Camera 3'));
        $this->incomePost($this->cb('room:Camera 5'));
        $res = $this->incomePost($this->cb('rooms:confirm'));
        $res->assertOk();
        $this->assertEquals('waiting_amount', TelegramSession::first()->state);
        $rooms = TelegramSession::first()->data['rooms'];
        $this->assertContains('Camera 3', $rooms);
        $this->assertContains('Camera 5', $rooms);
    }

    public function test_hotel_confirm_without_room_returns_warning(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:hotel'));
        $this->incomePost($this->cb('property:skycenter'));
        $res = $this->incomePost($this->cb('rooms:confirm'));
        $res->assertOk();
        $this->assertStringContainsString('selectează', strtolower($res->json('text')));
        $this->assertEquals('selecting_rooms', TelegramSession::first()->state);
    }

    public function test_rent_flow_description_then_amount(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:rent'));
        $res = $this->incomePost($this->msg('Seat Bogdan'));
        $res->assertOk();
        $this->assertEquals('waiting_amount', TelegramSession::first()->state);
        $this->assertEquals('Seat Bogdan', TelegramSession::first()->data['description']);
    }

    public function test_expired_session_resets_wizard(): void
    {
        $session = TelegramSession::create([
            'chat_id'    => '-100111',
            'user_id'    => '1001',
            'username'   => 'TestUser',
            'group_type' => 'income',
            'state'      => 'waiting_amount',
            'data'       => ['service' => 'parking', 'plate' => 'BX1234'],
            'expires_at' => now()->subMinutes(5),
        ]);

        $res = $this->incomePost($this->msg('250'));
        $res->assertOk();
        // Should have reset, not proceeded to payment selection
        $this->assertEquals('selecting_service', TelegramSession::first()->state);
        $this->assertStringContainsString('expirat', strtolower($res->json('text')));
    }

    public function test_missing_token_returns_401(): void
    {
        $res = $this->postJson('/api/automation/telegram/income', $this->msg('hello'));
        $res->assertStatus(401);
    }

    public function test_decimal_amount_with_comma_is_accepted(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:parking'));
        $this->incomePost($this->msg('BX 1234 AB'));
        $res = $this->incomePost($this->msg('222,60'));
        $res->assertOk();
        $this->assertEquals('selecting_payment', TelegramSession::first()->state);
        $this->assertEquals(222.60, TelegramSession::first()->data['amount']);
    }

    public function test_eur_payment_saves_eur_currency(): void
    {
        $this->incomePost($this->msg('start'));
        $this->incomePost($this->cb('service:parking'));
        $this->incomePost($this->msg('BX 1234 AB'));
        $this->incomePost($this->msg('125'));
        $res = $this->incomePost($this->cb('payment:eur'));
        $res->assertOk();
        $this->assertDatabaseHas('budget_transactions', [
            'currency' => 'EUR',
            'amount'   => 125.00,
        ]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec -T app php artisan test --filter=IncomeTelegramWizardTest`
Expected: FAIL — route not found / class not found.

- [ ] **Step 3: Create `ProcessIncomeTelegramUpdate` action**

Create `app/Actions/Telegram/ProcessIncomeTelegramUpdate.php`:

```php
<?php

namespace App\Actions\Telegram;

use App\Models\BudgetRawMessage;
use App\Models\BudgetTransaction;
use App\Models\LodgingProperty;
use App\Models\TelegramSession;

class ProcessIncomeTelegramUpdate
{
    public function handle(array $update): array
    {
        $chatId   = $update['chat_id'];
        $userId   = $update['user_id'];
        $type     = $update['update_type'];
        $text     = trim($update['text'] ?? '');
        $cbData   = $update['callback_data'] ?? null;
        $username = $update['username'] ?? null;

        $session = TelegramSession::where('chat_id', $chatId)
            ->where('user_id', $userId)
            ->first();

        // Expired or missing session → reset
        if ($session && $session->isExpired()) {
            $session->delete();
            $session = null;
        }

        if (! $session) {
            $session = TelegramSession::create([
                'chat_id'    => $chatId,
                'user_id'    => $userId,
                'username'   => $username,
                'group_type' => 'income',
                'state'      => 'selecting_service',
                'data'       => [],
                'expires_at' => now()->addMinutes(30),
            ]);

            if ($session->wasRecentlyCreated && isset($update['_reset'])) {
                // Expired reset — announce
                return $this->selectServicePrompt($session, expired: true);
            }

            return $this->selectServicePrompt($session);
        }

        // Refresh expiry on every interaction
        $session->expires_at = now()->addMinutes(30);

        return match ($session->state) {
            'selecting_service' => $this->handleSelectService($session, $type, $cbData),
            'waiting_plate'     => $this->handleWaitingPlate($session, $type, $text),
            'selecting_property'=> $this->handleSelectProperty($session, $type, $cbData),
            'selecting_rooms'   => $this->handleSelectRooms($session, $type, $cbData),
            'waiting_rent_desc' => $this->handleWaitingRentDesc($session, $type, $text),
            'waiting_amount'    => $this->handleWaitingAmount($session, $type, $text),
            'selecting_payment' => $this->handleSelectPayment($session, $type, $cbData),
            default             => $this->selectServicePrompt($session),
        };
    }

    // ── State handlers ─────────────────────────────────────────────────────────

    private function handleSelectService(TelegramSession $s, string $type, ?string $cbData): array
    {
        if ($type !== 'callback_query' || ! str_starts_with((string)$cbData, 'service:')) {
            return $this->selectServicePrompt($s);
        }

        $service = substr($cbData, 8); // 'parking' | 'hotel' | 'rent'

        $s->mergeData(['service' => $service]);

        return match ($service) {
            'parking' => $this->transition($s, 'waiting_plate',
                "🚗 Introdu numărul de înmatriculare:"),
            'hotel'   => $this->transition($s, 'selecting_property',
                "🏨 Selectează proprietatea:", $this->propertyKeyboard()),
            'rent'    => $this->transition($s, 'waiting_rent_desc',
                "🚙 Descriere scurtă (client / vehicul):"),
            default   => $this->selectServicePrompt($s),
        };
    }

    private function handleWaitingPlate(TelegramSession $s, string $type, string $text): array
    {
        if ($type !== 'message' || $text === '') {
            return $this->noChange($s, "🚗 Introdu numărul de înmatriculare:");
        }
        $s->mergeData(['plate' => mb_substr($text, 0, 32)]);
        return $this->transition($s, 'waiting_amount', "💵 Sumă încasată?");
    }

    private function handleSelectProperty(TelegramSession $s, string $type, ?string $cbData): array
    {
        if ($type !== 'callback_query' || ! str_starts_with((string)$cbData, 'property:')) {
            return $this->noChange($s, "🏨 Selectează proprietatea:", $this->propertyKeyboard());
        }
        $propertySlug = substr($cbData, 9); // 'skycenter' | 'serafim'
        $property = LodgingProperty::where('is_active', true)->get()
            ->first(fn($p) => str($p->name)->slug() == $propertySlug);

        if (! $property) {
            return $this->noChange($s, "🏨 Selectează proprietatea:", $this->propertyKeyboard());
        }

        $rooms = $property->rooms()->where('is_active', true)->pluck('name')->toArray();
        $s->mergeData(['property' => $property->name, 'rooms_available' => $rooms, 'rooms' => []]);

        return $this->transition($s, 'selecting_rooms',
            "🛏 Camera(ele) — bifează și apasă ✅:",
            $this->roomsKeyboard($rooms, []));
    }

    private function handleSelectRooms(TelegramSession $s, string $type, ?string $cbData): array
    {
        $available = $s->data['rooms_available'] ?? [];
        $selected  = $s->data['rooms'] ?? [];

        if ($type === 'callback_query' && $cbData === 'rooms:confirm') {
            if (empty($selected)) {
                return $this->noChange($s,
                    "⚠️ Selectează cel puțin o cameră.",
                    $this->roomsKeyboard($available, $selected));
            }
            return $this->transition($s, 'waiting_amount', "💵 Sumă totală?");
        }

        if ($type === 'callback_query' && str_starts_with((string)$cbData, 'room:')) {
            $room = substr($cbData, 5);
            if (in_array($room, $selected)) {
                $selected = array_values(array_diff($selected, [$room]));
            } elseif (in_array($room, $available)) {
                $selected[] = $room;
            }
            $s->mergeData(['rooms' => $selected]);
            $s->save();
            return $this->noChange($s,
                "🛏 Camera(ele) — bifează și apasă ✅:",
                $this->roomsKeyboard($available, $selected));
        }

        return $this->noChange($s,
            "🛏 Camera(ele) — bifează și apasă ✅:",
            $this->roomsKeyboard($available, $selected));
    }

    private function handleWaitingRentDesc(TelegramSession $s, string $type, string $text): array
    {
        if ($type !== 'message' || $text === '') {
            return $this->noChange($s, "🚙 Descriere scurtă (client / vehicul):");
        }
        $s->mergeData(['description' => mb_substr($text, 0, 128)]);
        return $this->transition($s, 'waiting_amount', "💵 Sumă?");
    }

    private function handleWaitingAmount(TelegramSession $s, string $type, string $text): array
    {
        if ($type !== 'message') {
            return $this->noChange($s, "💵 Introdu suma:");
        }
        $normalized = str_replace(',', '.', $text);
        if (! preg_match('/^\d{1,8}(\.\d{1,2})?$/', $normalized)) {
            return $this->noChange($s,
                "❌ Sumă invalidă. Introdu un număr (ex: 250 sau 75.50):");
        }
        $s->mergeData(['amount' => (float)$normalized]);
        return $this->transition($s, 'selecting_payment',
            "💳 Metoda de plată:", $this->paymentKeyboard());
    }

    private function handleSelectPayment(TelegramSession $s, string $type, ?string $cbData): array
    {
        $methods = ['cash' => 'Cash RON', 'card' => 'Card RON', 'eur' => 'EUR cash', 'usd' => 'USD cash', 'transfer' => 'Transfer'];
        if ($type !== 'callback_query' || ! str_starts_with((string)$cbData, 'payment:')) {
            return $this->noChange($s, "💳 Metoda de plată:", $this->paymentKeyboard());
        }
        $method = substr($cbData, 8);
        if (! array_key_exists($method, $methods)) {
            return $this->noChange($s, "💳 Metoda de plată:", $this->paymentKeyboard());
        }

        $data    = $s->data;
        $service = $data['service'];
        $amount  = (float)($data['amount']);
        $currency = match ($method) {
            'eur'   => 'EUR',
            'usd'   => 'USD',
            default => 'RON',
        };
        $paymentMethod = match ($method) {
            'card'     => 'card',
            'transfer' => 'transfer',
            default    => 'cash',
        };

        $description = match ($service) {
            'parking' => 'Parcare ' . ($data['plate'] ?? ''),
            'hotel'   => ($data['property'] ?? 'Hotel') . ' ' . implode('+', $data['rooms'] ?? []),
            'rent'    => 'Rent-a-car: ' . ($data['description'] ?? ''),
            default   => $service,
        };

        // Save raw message
        $raw = BudgetRawMessage::create([
            'chat_id'     => $s->chat_id,
            'message_id'  => 'bot-session-' . $s->id,
            'text'        => "[INCOME] {$description} - {$amount} {$currency} ({$paymentMethod})",
            'parsed'      => true,
            'received_at' => now(),
        ]);

        // Save transaction
        $serviceMap = ['parking' => 'parcare', 'hotel' => 'hotel', 'rent' => 'rent'];
        BudgetTransaction::create([
            'type'         => 'income',
            'service'      => $serviceMap[$service] ?? $service,
            'amount'       => $amount,
            'currency'     => $currency,
            'occurred_on'  => now()->toDateString(),
            'description'  => $description,
            'telegram_chat'=> 'income',
            'raw_message_id'=> $raw->id,
            'metadata'     => [
                'payment_method' => $paymentMethod,
                'telegram_user'  => $s->username,
                'wizard_data'    => $data,
            ],
        ]);

        $chatId = $s->chat_id;
        $s->delete();

        return [
            'action'     => 'send',
            'chat_id'    => $chatId,
            'message_id' => null,
            'text'       => "✅ Salvat!\n{$description} — {$amount} {$currency} (" . $methods[$method] . ")\nData: " . now()->format('d.m.Y H:i'),
            'keyboard'   => null,
        ];
    }

    // ── Keyboard builders ──────────────────────────────────────────────────────

    private function selectServicePrompt(TelegramSession $s, bool $expired = false): array
    {
        $s->state = 'selecting_service';
        $s->data  = [];
        $s->expires_at = now()->addMinutes(30);
        $s->save();

        $prefix = $expired ? "⏰ Sesiunea a expirat. Reîncepem:\n\n" : '';
        return [
            'action'     => 'send',
            'chat_id'    => $s->chat_id,
            'message_id' => null,
            'text'       => $prefix . "💰 Ce tip de încasare înregistrezi?",
            'keyboard'   => ['inline_keyboard' => [[
                ['text' => '🚗 Parcare',    'callback_data' => 'service:parking'],
                ['text' => '🏨 Hotel',      'callback_data' => 'service:hotel'],
                ['text' => '🚙 Rent-a-car', 'callback_data' => 'service:rent'],
            ]]],
        ];
    }

    private function propertyKeyboard(): array
    {
        $props = LodgingProperty::where('is_active', true)->get();
        $buttons = $props->map(fn($p) => [
            'text'          => $p->name,
            'callback_data' => 'property:' . str($p->name)->slug(),
        ])->toArray();

        return ['inline_keyboard' => [array_values($buttons)]];
    }

    private function roomsKeyboard(array $available, array $selected): array
    {
        $buttons = array_map(fn($room) => [
            'text'          => in_array($room, $selected) ? "☑ {$room}" : $room,
            'callback_data' => "room:{$room}",
        ], $available);

        $rows   = array_chunk($buttons, 4);
        $rows[] = [['text' => '✅ Confirmă selecția', 'callback_data' => 'rooms:confirm']];

        return ['inline_keyboard' => $rows];
    }

    private function paymentKeyboard(): array
    {
        return ['inline_keyboard' => [
            [
                ['text' => 'Cash RON',       'callback_data' => 'payment:cash'],
                ['text' => 'Card RON',        'callback_data' => 'payment:card'],
            ],
            [
                ['text' => 'EUR cash',        'callback_data' => 'payment:eur'],
                ['text' => 'USD cash',        'callback_data' => 'payment:usd'],
                ['text' => 'Transfer bancar', 'callback_data' => 'payment:transfer'],
            ],
        ]];
    }

    private function transition(TelegramSession $s, string $state, string $text, ?array $keyboard = null): array
    {
        $s->state = $state;
        $s->save();

        return [
            'action'     => 'send',
            'chat_id'    => $s->chat_id,
            'message_id' => $s->wizard_message_id,
            'text'       => $text,
            'keyboard'   => $keyboard,
        ];
    }

    private function noChange(TelegramSession $s, string $text, ?array $keyboard = null): array
    {
        $s->save();

        return [
            'action'     => 'send',
            'chat_id'    => $s->chat_id,
            'message_id' => null,
            'text'       => $text,
            'keyboard'   => $keyboard,
        ];
    }
}
```

- [ ] **Step 4: Run tests to verify they still fail (no route yet)**

Run: `docker compose exec -T app php artisan test --filter=IncomeTelegramWizardTest`
Expected: FAIL — route not found (404).

- [ ] **Step 5: Commit action**

```
git add app/Actions/Telegram/ProcessIncomeTelegramUpdate.php
git add tests/Feature/Telegram/IncomeTelegramWizardTest.php
git commit -m "feat(telegram): add income wizard action and tests"
```

---

### Task 4: Expense wizard — `ProcessExpenseTelegramUpdate` action

**Files:**
- Create: `app/Actions/Telegram/ProcessExpenseTelegramUpdate.php`
- Test: `tests/Feature/Telegram/ExpenseTelegramWizardTest.php`

---

- [ ] **Step 1: Write failing tests for expense wizard**

Create `tests/Feature/Telegram/ExpenseTelegramWizardTest.php`:

```php
<?php

namespace Tests\Feature\Telegram;

use App\Models\BudgetTransaction;
use App\Models\TelegramSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTelegramWizardTest extends TestCase
{
    use RefreshDatabase;

    private function expensePost(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . config('automation.token'),
        ])->postJson('/api/automation/telegram/expense', $payload);
    }

    private function msg(string $text, string $chatId = '-100222', string $userId = '2001'): array
    {
        return [
            'update_type' => 'message',
            'chat_id'     => $chatId,
            'user_id'     => $userId,
            'username'    => 'TestUser',
            'message_id'  => rand(1, 9999),
            'text'        => $text,
            'callback_query_id' => null,
            'callback_data'     => null,
        ];
    }

    private function cb(string $data, string $chatId = '-100222', string $userId = '2001'): array
    {
        return [
            'update_type'       => 'callback_query',
            'chat_id'           => $chatId,
            'user_id'           => $userId,
            'username'          => 'TestUser',
            'message_id'        => 50,
            'text'              => null,
            'callback_query_id' => 'cbq456',
            'callback_data'     => $data,
        ];
    }

    public function test_fresh_message_shows_category_selection(): void
    {
        $res = $this->expensePost($this->msg('hello'));
        $res->assertOk();
        $this->assertEquals('send', $res->json('action'));
        $this->assertStringContainsString('categor', strtolower($res->json('text')));
        $this->assertNotNull($res->json('keyboard'));
        $this->assertEquals('selecting_category', TelegramSession::first()->state);
    }

    public function test_standard_category_selection_prompts_amount(): void
    {
        $this->expensePost($this->msg('start'));
        // category:1 means first budget_category id
        $catId = \App\Models\BudgetCategory::where('name', 'Kaufland')->value('id');
        $res = $this->expensePost($this->cb("category:{$catId}"));
        $res->assertOk();
        $this->assertStringContainsString('sumă', strtolower($res->json('text')));
        $this->assertEquals('waiting_expense_amount', TelegramSession::first()->state);
    }

    public function test_custom_category_prompts_description(): void
    {
        $this->expensePost($this->msg('start'));
        $res = $this->expensePost($this->cb('category:custom'));
        $res->assertOk();
        $this->assertStringContainsString('descriere', strtolower($res->json('text')));
        $this->assertEquals('waiting_custom_desc', TelegramSession::first()->state);
    }

    public function test_custom_description_then_amount(): void
    {
        $this->expensePost($this->msg('start'));
        $this->expensePost($this->cb('category:custom'));
        $res = $this->expensePost($this->msg('700 uși'));
        $res->assertOk();
        $this->assertEquals('waiting_expense_amount', TelegramSession::first()->state);
        $this->assertEquals('700 uși', TelegramSession::first()->data['custom_desc']);
    }

    public function test_amount_saves_expense_transaction(): void
    {
        $this->expensePost($this->msg('start'));
        $catId = \App\Models\BudgetCategory::where('name', 'Curățenie')->value('id');
        $this->expensePost($this->cb("category:{$catId}"));
        $res = $this->expensePost($this->msg('300'));
        $res->assertOk();
        $this->assertStringContainsString('salvat', strtolower($res->json('text')));
        $this->assertDatabaseMissing('telegram_sessions', ['chat_id' => '-100222']);
        $this->assertDatabaseHas('budget_transactions', [
            'type'        => 'expense',
            'category_id' => $catId,
            'amount'      => 300.00,
            'currency'    => 'RON',
        ]);
    }

    public function test_invalid_amount_keeps_state(): void
    {
        $this->expensePost($this->msg('start'));
        $catId = \App\Models\BudgetCategory::where('name', 'Kaufland')->value('id');
        $this->expensePost($this->cb("category:{$catId}"));
        $res = $this->expensePost($this->msg('doua sute'));
        $res->assertOk();
        $this->assertStringContainsString('invalid', strtolower($res->json('text')));
        $this->assertEquals('waiting_expense_amount', TelegramSession::first()->state);
    }

    public function test_expired_session_resets(): void
    {
        TelegramSession::create([
            'chat_id'    => '-100222',
            'user_id'    => '2001',
            'username'   => 'TestUser',
            'group_type' => 'expense',
            'state'      => 'waiting_expense_amount',
            'data'       => ['category_id' => 1, 'category_name' => 'Kaufland'],
            'expires_at' => now()->subMinutes(5),
        ]);
        $res = $this->expensePost($this->msg('300'));
        $res->assertOk();
        $this->assertEquals('selecting_category', TelegramSession::first()->state);
        $this->assertStringContainsString('expirat', strtolower($res->json('text')));
    }

    public function test_missing_token_returns_401(): void
    {
        $res = $this->postJson('/api/automation/telegram/expense', $this->msg('hello'));
        $res->assertStatus(401);
    }

    public function test_comma_decimal_amount_accepted(): void
    {
        $this->expensePost($this->msg('start'));
        $catId = \App\Models\BudgetCategory::where('name', 'Kaufland')->value('id');
        $this->expensePost($this->cb("category:{$catId}"));
        $res = $this->expensePost($this->msg('151,20'));
        $res->assertOk();
        $this->assertDatabaseHas('budget_transactions', ['amount' => 151.20]);
    }
}
```

- [ ] **Step 2: Create `ProcessExpenseTelegramUpdate` action**

Create `app/Actions/Telegram/ProcessExpenseTelegramUpdate.php`:

```php
<?php

namespace App\Actions\Telegram;

use App\Models\BudgetCategory;
use App\Models\BudgetRawMessage;
use App\Models\BudgetTransaction;
use App\Models\TelegramSession;

class ProcessExpenseTelegramUpdate
{
    public function handle(array $update): array
    {
        $chatId   = $update['chat_id'];
        $userId   = $update['user_id'];
        $type     = $update['update_type'];
        $text     = trim($update['text'] ?? '');
        $cbData   = $update['callback_data'] ?? null;
        $username = $update['username'] ?? null;

        $session = TelegramSession::where('chat_id', $chatId)
            ->where('user_id', $userId)
            ->first();

        if ($session && $session->isExpired()) {
            $session->delete();
            $session = null;
        }

        if (! $session) {
            $session = TelegramSession::create([
                'chat_id'    => $chatId,
                'user_id'    => $userId,
                'username'   => $username,
                'group_type' => 'expense',
                'state'      => 'selecting_category',
                'data'       => [],
                'expires_at' => now()->addMinutes(30),
            ]);
            return $this->categoryPrompt($session);
        }

        $session->expires_at = now()->addMinutes(30);

        return match ($session->state) {
            'selecting_category'     => $this->handleSelectCategory($session, $type, $cbData),
            'waiting_custom_desc'    => $this->handleCustomDesc($session, $type, $text),
            'waiting_expense_amount' => $this->handleAmount($session, $type, $text),
            default                  => $this->categoryPrompt($session),
        };
    }

    private function handleSelectCategory(TelegramSession $s, string $type, ?string $cbData): array
    {
        if ($type !== 'callback_query') {
            return $this->categoryPrompt($s);
        }

        if ($cbData === 'category:custom') {
            return $this->transition($s, 'waiting_custom_desc', "✏️ Scrie descrierea cheltuielii:");
        }

        if (str_starts_with((string)$cbData, 'category:')) {
            $catId = (int)substr($cbData, 9);
            $cat   = BudgetCategory::find($catId);
            if (! $cat) {
                return $this->categoryPrompt($s);
            }
            $s->mergeData(['category_id' => $cat->id, 'category_name' => $cat->name]);
            return $this->transition($s, 'waiting_expense_amount', "💵 Sumă (RON):");
        }

        return $this->categoryPrompt($s);
    }

    private function handleCustomDesc(TelegramSession $s, string $type, string $text): array
    {
        if ($type !== 'message' || $text === '') {
            return $this->noChange($s, "✏️ Scrie descrierea cheltuielii:");
        }
        $s->mergeData(['custom_desc' => mb_substr($text, 0, 128)]);
        return $this->transition($s, 'waiting_expense_amount', "💵 Sumă (RON):");
    }

    private function handleAmount(TelegramSession $s, string $type, string $text): array
    {
        if ($type !== 'message') {
            return $this->noChange($s, "💵 Sumă (RON):");
        }
        $normalized = str_replace(',', '.', $text);
        if (! preg_match('/^\d{1,8}(\.\d{1,2})?$/', $normalized)) {
            return $this->noChange($s, "❌ Sumă invalidă. Introdu un număr (ex: 300 sau 151.20):");
        }

        $amount  = (float)$normalized;
        $data    = $s->data;
        $catId   = $data['category_id'] ?? null;
        $catName = $data['category_name'] ?? ($data['custom_desc'] ?? 'Cheltuială');
        $desc    = $data['custom_desc'] ?? $catName;

        $raw = BudgetRawMessage::create([
            'chat_id'     => $s->chat_id,
            'message_id'  => 'bot-session-' . $s->id,
            'text'        => "[EXPENSE] {$desc} - {$amount} RON",
            'parsed'      => true,
            'received_at' => now(),
        ]);

        BudgetTransaction::create([
            'type'          => 'expense',
            'category_id'   => $catId,
            'service'       => $catId ? BudgetCategory::find($catId)?->service : 'general',
            'amount'        => $amount,
            'currency'      => 'RON',
            'occurred_on'   => now()->toDateString(),
            'description'   => $desc,
            'telegram_chat' => 'expense',
            'raw_message_id'=> $raw->id,
            'metadata'      => ['telegram_user' => $s->username],
        ]);

        $chatId = $s->chat_id;
        $s->delete();

        return [
            'action'     => 'send',
            'chat_id'    => $chatId,
            'message_id' => null,
            'text'       => "✅ Salvat!\n{$desc} — {$amount} RON\nData: " . now()->format('d.m.Y H:i'),
            'keyboard'   => null,
        ];
    }

    private function categoryPrompt(TelegramSession $s, bool $expired = false): array
    {
        $s->state = 'selecting_category';
        $s->data  = [];
        $s->expires_at = now()->addMinutes(30);
        $s->save();

        $categories = BudgetCategory::where('is_active', true)
            ->where('kind', 'expense')
            ->orderBy('service')
            ->orderBy('name')
            ->get();

        $buttons = $categories->map(fn($c) => [
            'text'          => ($c->emoji . ' ' . $c->name),
            'callback_data' => "category:{$c->id}",
        ])->toArray();

        $rows   = array_chunk($buttons, 3);
        $rows[] = [['text' => '✏️ Altele...', 'callback_data' => 'category:custom']];

        $prefix = $expired ? "⏰ Sesiunea a expirat. Reîncepem:\n\n" : '';

        return [
            'action'     => 'send',
            'chat_id'    => $s->chat_id,
            'message_id' => null,
            'text'       => $prefix . "📤 Selectează categoria cheltuielii:",
            'keyboard'   => ['inline_keyboard' => $rows],
        ];
    }

    private function transition(TelegramSession $s, string $state, string $text): array
    {
        $s->state = $state;
        $s->save();
        return ['action' => 'send', 'chat_id' => $s->chat_id, 'message_id' => null, 'text' => $text, 'keyboard' => null];
    }

    private function noChange(TelegramSession $s, string $text): array
    {
        $s->save();
        return ['action' => 'send', 'chat_id' => $s->chat_id, 'message_id' => null, 'text' => $text, 'keyboard' => null];
    }
}
```

- [ ] **Step 3: Commit**

```
git add app/Actions/Telegram/ProcessExpenseTelegramUpdate.php
git add tests/Feature/Telegram/ExpenseTelegramWizardTest.php
git commit -m "feat(telegram): add expense wizard action and tests"
```

---

### Task 5: Controllers + Routes

**Files:**
- Create: `app/Http/Controllers/Api/Automation/TelegramIncomeController.php`
- Create: `app/Http/Controllers/Api/Automation/TelegramExpenseController.php`
- Modify: `routes/api.php`

---

- [ ] **Step 1: Create `TelegramIncomeController`**

Create `app/Http/Controllers/Api/Automation/TelegramIncomeController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Automation;

use App\Actions\Telegram\ProcessIncomeTelegramUpdate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramIncomeController extends Controller
{
    public function __invoke(Request $request, ProcessIncomeTelegramUpdate $action): JsonResponse
    {
        $validated = $request->validate([
            'update_type'       => 'required|in:message,callback_query',
            'chat_id'           => 'required|string',
            'user_id'           => 'required|string',
            'username'          => 'nullable|string',
            'message_id'        => 'nullable|integer',
            'text'              => 'nullable|string|max:512',
            'callback_query_id' => 'nullable|string',
            'callback_data'     => 'nullable|string|max:64',
        ]);

        $result = $action->handle($validated);

        return response()->json($result);
    }
}
```

- [ ] **Step 2: Create `TelegramExpenseController`**

Create `app/Http/Controllers/Api/Automation/TelegramExpenseController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Automation;

use App\Actions\Telegram\ProcessExpenseTelegramUpdate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramExpenseController extends Controller
{
    public function __invoke(Request $request, ProcessExpenseTelegramUpdate $action): JsonResponse
    {
        $validated = $request->validate([
            'update_type'       => 'required|in:message,callback_query',
            'chat_id'           => 'required|string',
            'user_id'           => 'required|string',
            'username'          => 'nullable|string',
            'message_id'        => 'nullable|integer',
            'text'              => 'nullable|string|max:512',
            'callback_query_id' => 'nullable|string',
            'callback_data'     => 'nullable|string|max:64',
        ]);

        $result = $action->handle($validated);

        return response()->json($result);
    }
}
```

- [ ] **Step 3: Add routes**

In `routes/api.php`, inside the `automation.token` middleware group, add:

```php
Route::post('telegram/income',  \App\Http\Controllers\Api\Automation\TelegramIncomeController::class);
Route::post('telegram/expense', \App\Http\Controllers\Api\Automation\TelegramExpenseController::class);
```

- [ ] **Step 4: Run all Telegram tests to verify they pass**

Run: `docker compose exec -T app php artisan test --filter=TelegramWizardTest`
Expected: all tests PASS (both income and expense suites).

- [ ] **Step 5: Commit**

```
git add app/Http/Controllers/Api/Automation/TelegramIncomeController.php
git add app/Http/Controllers/Api/Automation/TelegramExpenseController.php
git add routes/api.php
git commit -m "feat(automation): add telegram income and expense API endpoints"
```

---

### Task 6: Full verification + n8n setup documentation

**Files:**
- Create: `docs/telegram-bot-setup.md`

---

- [ ] **Step 1: Run full test suite**

Run: `docker compose exec -T app php artisan test`
Expected: all tests pass, count increased from 119.

- [ ] **Step 2: Verify migrate:fresh --seed works cleanly**

Run: `docker compose exec -T app php artisan migrate:fresh --seed --force`
Expected: completes without errors, `budget_categories` has 14 rows.

- [ ] **Step 3: Create setup documentation**

Create `docs/telegram-bot-setup.md`:

```markdown
# Telegram Bot Setup Guide

## Overview

Two separate Telegram bots are required — one per group:
- **Bot A** (`@skycenter_income_bot`) — grupul de încasări
- **Bot B** (`@skycenter_expense_bot`) — grupul de cheltuieli

## Step 1: Create the bots via @BotFather

1. Open Telegram and message `@BotFather`
2. Run `/newbot` for each bot and follow prompts
3. Copy the API token for each bot

## Step 2: Configure .env

Add to `.env`:
```
TELEGRAM_INCOME_BOT_TOKEN=<token_for_bot_A>
TELEGRAM_EXPENSE_BOT_TOKEN=<token_for_bot_B>
```

## Step 3: Add bots to groups

1. Add each bot to its respective Telegram group
2. Grant the bot permission to send messages and read messages (disable privacy mode via @BotFather → `/setprivacy` → Disable, for group message reading)

## Step 4: Configure n8n workflows

Two n8n workflows required (one per bot). Each workflow has ~5 nodes:

### Node 1 — Telegram Trigger
- Credential: Bot token
- Updates to receive: `message`, `callback_query`

### Node 2 — Switch
- Condition A: `{{ $json.body.callback_query }}` is not empty → route to Node 3 (callback path)
- Condition B: default → skip Node 3

### Node 3 — Telegram: Answer Callback Query (callback path only)
- Operation: Answer Callback Query
- Callback Query ID: `{{ $json.body.callback_query.id }}`

### Node 4 — HTTP Request (POST to Laravel)
- Method: POST
- URL: `http://localhost/api/automation/telegram/income` (or `/expense`)
- Headers: `Authorization: Bearer <AUTOMATION_API_TOKEN>`
- Body (JSON):
```json
{
  "update_type": "{{ $json.body.callback_query ? 'callback_query' : 'message' }}",
  "chat_id": "{{ $json.body.message.chat.id ?? $json.body.callback_query.message.chat.id }}",
  "user_id": "{{ $json.body.message.from.id ?? $json.body.callback_query.from.id }}",
  "username": "{{ $json.body.message.from.username ?? $json.body.callback_query.from.username }}",
  "message_id": "{{ $json.body.message.message_id ?? $json.body.callback_query.message.message_id }}",
  "text": "{{ $json.body.message.text ?? '' }}",
  "callback_query_id": "{{ $json.body.callback_query.id ?? '' }}",
  "callback_data": "{{ $json.body.callback_query.data ?? '' }}"
}
```

### Node 5 — Telegram: Send Message
- Operation: Send Message
- Chat ID: `{{ $json.chat_id }}`
- Text: `{{ $json.text }}`
- Reply Markup (if `$json.keyboard` is not null): paste `{{ JSON.stringify($json.keyboard) }}`
- Parse Mode: HTML (optional)

## Step 5: Activate workflows

Activate both workflows in n8n. Test by sending a message to each group.
```

- [ ] **Step 4: Commit everything**

```
git add docs/telegram-bot-setup.md
git commit -m "docs: add Telegram bot n8n setup guide"
```

- [ ] **Step 5: Final commit summary**

Run: `git log --oneline -8`
Expected output shows 6 new commits since `a695106` (spec):
- `feat(db): add telegram_sessions...`
- `feat(models): add TelegramSession...`
- `feat(telegram): add income wizard...`
- `feat(telegram): add expense wizard...`
- `feat(automation): add telegram income and expense API endpoints`
- `docs: add Telegram bot n8n setup guide`
