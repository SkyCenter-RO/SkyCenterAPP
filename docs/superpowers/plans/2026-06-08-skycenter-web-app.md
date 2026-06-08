# Sky Center — Web App Intern (Subproiect #2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construiește un panou de administrare intern (Filament 5) peste schema PostgreSQL existentă, cu CRUD complet pe toate entitățile (parcare, cazare, rent-a-car, buget, plăți, mesagerie) + o pagină "Ordinea de zi" și control de acces admin/operator.

**Architecture:** Un singur panou Filament 5 (`/admin`) cu login standard. Pentru fiecare tabel de domeniu există un model Eloquent și (în general) o resursă Filament, grupate în navigare pe servicii. Tabelele dependente (imagini, audit-uri, sync-links, zone) apar ca Relation Manager pe resursa părinte. Accesul la Buget/Salarii/Plăți și la gestionarea utilizatorilor e restricționat la rolul `admin`. "Ordinea de zi" e o pagină custom (ecran principal) care agregă evenimentele zilei și disponibilitatea.

**Tech Stack:** Laravel 12, PHP 8.3, PostgreSQL 16, Filament 5.6, Pest/PHPUnit 11, Docker Compose. Toate comenzile rulează prin `docker compose exec app <comandă>` (sau `-T` pentru neinteractiv).

---

## Convenții generale (citește înainte de a începe)

- **Toate** comenzile artisan/composer/test rulează în container: `docker compose exec app php artisan ...`, `docker compose exec -T app php artisan test ...`. Niciodată pe host.
- Schema bazei de date **există deja** (subproiectul #1) și NU se modifică în acest subproiect. Migrațiile sunt sursa de adevăr pentru coloane — sunt în `database/migrations/2026_06_06_*`.
- Commit-urile se fac pe ramura `feat/web-app` (creată în Task 1), autor `Sky Center <infinitive.gen@gmail.com>`, cu trailer `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Format comandă:
  ```bash
  git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
  <subiect>

  Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
  EOF
  )"
  ```
- Modelele Eloquent se mapează **conform convenției** la numele tabelelor (ex: `ParkingReservation` → `parking_reservations`, `Salary` → `salaries`, `LodgingProperty` → `lodging_properties`). Nu e nevoie de `$table` explicit nicăieri.
- Limba UI: **română** (etichete, titluri de navigare). Datele de test pot folosi diacritice (`apă`, `dubă`).
- Diferență față de proiectul vecin Ops: NU reutilizăm cod/DB din Ops. Schema noastră are alte nume de coloane (ex: `check_in_at`/`check_out_at` la parcare, `zone_id` pe `parking_spaces`). Sursa de adevăr e exclusiv `database/migrations/` din acest repo.

## Structura de fișiere (rezultat final)

```
app/
  Models/
    User.php                       (modificat — FilamentUser, roluri)
    ParkingLot.php ParkingZone.php ParkingCustomer.php ParkingSpace.php
    ParkingPrice.php ParkingReservation.php ParkingReservationImage.php ParkingStatusAudit.php
    LodgingProperty.php Room.php LodgingReservation.php LodgingSyncLink.php
    RentVehicle.php RentVehicleImage.php RentClient.php RentContract.php RentMaintenanceRecord.php
    Payment.php PaymentChangeAudit.php
    BudgetCategory.php BudgetRawMessage.php BudgetTransaction.php Salary.php
    MessageTemplate.php OutboundMessage.php AutomationWebhookLog.php AutomationEvent.php
  Providers/Filament/AdminPanelProvider.php   (nou — generat de filament:install)
  Filament/
    Resources/                     (≈18 resurse + Pages + RelationManagers)
    Pages/OrdineaDeZi.php          (pagina custom)
  Policies/                        (BudgetCategoryPolicy, ..., PaymentPolicy, UserPolicy)
resources/views/filament/pages/ordinea-de-zi.blade.php
database/seeders/AdminUserSeeder.php
tests/Feature/Panel/                (teste acces panou, CRUD, ordinea-de-zi)
```

---

## Task 1: Instalare Filament + panou + autentificare + model User

**Files:**
- Modify: `composer.json` (prin `composer require`)
- Create: `app/Providers/Filament/AdminPanelProvider.php` (generat)
- Modify: `app/Models/User.php`
- Create: `database/seeders/AdminUserSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Panel/PanelAccessTest.php`

- [ ] **Step 1: Creează ramura de lucru**

```bash
git checkout -b feat/web-app
```

- [ ] **Step 2: Instalează Filament 5**

```bash
docker compose exec -T app composer require filament/filament:"^5.6" --no-interaction
docker compose exec -T app php artisan filament:install --panels --no-interaction
```
Așteptat: se creează `app/Providers/Filament/AdminPanelProvider.php` și se înregistrează automat în `bootstrap/providers.php`. Comanda generează un panou cu id `admin`, path `/admin`.

- [ ] **Step 3: Scrie testul care eșuează (acces panou pe bază de rol)**

Create `tests/Feature/Panel/PanelAccessTest.php`:

```php
<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    private function panel()
    {
        return Filament::getPanel('admin');
    }

    public function test_active_admin_can_access_panel(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->assertTrue($user->canAccessPanel($this->panel()));
    }

    public function test_active_operator_can_access_panel(): void
    {
        $user = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $this->assertTrue($user->canAccessPanel($this->panel()));
    }

    public function test_inactive_user_cannot_access_panel(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => false]);
        $this->assertFalse($user->canAccessPanel($this->panel()));
    }

    public function test_role_helpers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'operator']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($operator->isAdmin());
        $this->assertTrue($operator->isOperator());
        $this->assertTrue($admin->hasAnyRole(['admin', 'operator']));
    }

    public function test_login_screen_is_reachable(): void
    {
        $this->get('/admin/login')->assertSuccessful();
    }
}
```

- [ ] **Step 4: Rulează testul (eșuează)**

Run: `docker compose exec -T app php artisan test --filter=PanelAccessTest`
Așteptat: FAIL — `canAccessPanel`/`isAdmin` nu există pe User.

- [ ] **Step 5: Actualizează modelul User**

Replace `app/Models/User.php` cu:

```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_OPERATOR = 'operator';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasValidRole();
    }

    /**
     * @return list<string>
     */
    public static function roles(): array
    {
        return [self::ROLE_ADMIN, self::ROLE_OPERATOR];
    }

    public function hasValidRole(): bool
    {
        return in_array($this->role, self::roles(), true);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isOperator(): bool
    {
        return $this->role === self::ROLE_OPERATOR;
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * @param  list<string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
```

- [ ] **Step 6: Actualizează UserFactory pentru noile coloane**

În `database/factories/UserFactory.php`, în array-ul `definition()`, adaugă după `'password' => ...` (sau `'remember_token' => ...`):

```php
            'phone' => null,
            'role' => 'operator',
            'is_active' => true,
```
(Restul factory-ului rămâne neschimbat.)

- [ ] **Step 7: Rulează testul (trece)**

Run: `docker compose exec -T app php artisan test --filter=PanelAccessTest`
Așteptat: PASS (5 teste).

- [ ] **Step 8: Creează seeder-ul pentru utilizatorul admin**

Create `database/seeders/AdminUserSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@skycenter.local'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('schimba-parola'),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ],
        );
    }
}
```

Adaugă `AdminUserSeeder::class` în array-ul `$this->call([...])` din `database/seeders/DatabaseSeeder.php`, ca prim element.

- [ ] **Step 9: Verifică migrarea + seed și commit**

```bash
docker compose exec -T app php artisan migrate:fresh --seed --force
docker compose exec -T app php artisan test --filter=PanelAccessTest
git add -A
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(panel): install Filament 5, panel auth + user roles

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```
Așteptat: migrate:fresh se termină cu `DONE`, testele trec.

---

## Task 2: Modele Eloquent — Parcare

**Files:**
- Create: `app/Models/ParkingLot.php`, `ParkingZone.php`, `ParkingCustomer.php`, `ParkingSpace.php`, `ParkingPrice.php`, `ParkingReservation.php`, `ParkingReservationImage.php`, `ParkingStatusAudit.php`
- Test: `tests/Feature/Panel/ParkingModelsTest.php`

- [ ] **Step 1: Scrie testul care eșuează**

Create `tests/Feature/Panel/ParkingModelsTest.php`:

```php
<?php

namespace Tests\Feature\Panel;

use App\Models\ParkingCustomer;
use App\Models\ParkingLot;
use App\Models\ParkingReservation;
use App\Models\ParkingReservationImage;
use App\Models\ParkingStatusAudit;
use App\Models\ParkingZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lot_has_zones_and_reservation_relations_work(): void
    {
        $lot = ParkingLot::create(['name' => 'Parcarea 1', 'total_spaces' => 54]);
        $zone = ParkingZone::create(['lot_id' => $lot->id, 'code' => 'A', 'capacity' => 11]);
        $customer = ParkingCustomer::create(['name' => 'Ion Popescu', 'phone' => '0700000000']);

        $reservation = ParkingReservation::create([
            'customer_id' => $customer->id,
            'lot_id' => $lot->id,
            'zone_id' => $zone->id,
            'status' => 'booked',
            'plate' => 'B 123 ABC',
            'vehicle_type' => 'autoturism',
            'keys_left' => true,
            'metadata' => ['note' => 'test'],
        ]);

        ParkingReservationImage::create(['parking_reservation_id' => $reservation->id, 'path' => 'img/1.jpg']);
        ParkingStatusAudit::create(['parking_reservation_id' => $reservation->id, 'to_status' => 'booked']);

        $this->assertSame(1, $lot->zones()->count());
        $this->assertSame('Parcarea 1', $reservation->lot->name);
        $this->assertSame('Ion Popescu', $reservation->customer->name);
        $this->assertSame('A', $reservation->zone->code);
        $this->assertSame(1, $reservation->images()->count());
        $this->assertSame(1, $reservation->statusAudits()->count());
        $this->assertTrue($reservation->keys_left);
        $this->assertSame(['note' => 'test'], $reservation->metadata);
    }
}
```

- [ ] **Step 2: Rulează testul (eșuează)**

Run: `docker compose exec -T app php artisan test --filter=ParkingModelsTest`
Așteptat: FAIL — clasa `ParkingLot` nu există.

- [ ] **Step 3: Creează modelele de parcare**

Create `app/Models/ParkingLot.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingLot extends Model
{
    protected $fillable = ['name', 'total_spaces', 'notes'];

    protected $casts = ['total_spaces' => 'integer'];

    public function zones(): HasMany
    {
        return $this->hasMany(ParkingZone::class, 'lot_id');
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(ParkingSpace::class, 'lot_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(ParkingReservation::class, 'lot_id');
    }
}
```

Create `app/Models/ParkingZone.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingZone extends Model
{
    protected $fillable = ['lot_id', 'code', 'capacity'];

    protected $casts = ['capacity' => 'integer'];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(ParkingSpace::class, 'zone_id');
    }
}
```

Create `app/Models/ParkingCustomer.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingCustomer extends Model
{
    protected $fillable = [
        'source', 'external_id', 'name', 'phone', 'normalized_phone', 'email', 'city', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function reservations(): HasMany
    {
        return $this->hasMany(ParkingReservation::class, 'customer_id');
    }
}
```

Create `app/Models/ParkingSpace.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingSpace extends Model
{
    protected $fillable = [
        'source', 'external_id', 'lot_id', 'zone_id', 'label', 'requires_keys',
        'vehicle_type_suitability', 'blocks_space_id', 'blocked_by_space_id',
        'xy_map_location', 'notes', 'metadata',
    ];

    protected $casts = [
        'requires_keys' => 'boolean',
        'metadata' => 'array',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ParkingZone::class, 'zone_id');
    }
}
```

Create `app/Models/ParkingPrice.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParkingPrice extends Model
{
    protected $fillable = [
        'source', 'external_id', 'vehicle_type', 'min_days', 'max_days',
        'price_per_day', 'fixed_price', 'currency', 'metadata',
    ];

    protected $casts = [
        'min_days' => 'integer',
        'max_days' => 'integer',
        'price_per_day' => 'decimal:2',
        'fixed_price' => 'decimal:2',
        'metadata' => 'array',
    ];
}
```

Create `app/Models/ParkingReservation.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingReservation extends Model
{
    protected $fillable = [
        'source', 'external_id', 'customer_id', 'lot_id', 'zone_id', 'parking_space_id',
        'status', 'plate', 'normalized_plate', 'vehicle_type', 'check_in_at', 'check_out_at',
        'days', 'adults', 'children', 'keys_left', 'cost', 'quoted_price', 'currency',
        'paid', 'notes', 'review_request_sent', 'source_created_at', 'metadata',
        'created_by_id', 'updated_by_id',
    ];

    protected $casts = [
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'source_created_at' => 'datetime',
        'days' => 'decimal:2',
        'adults' => 'integer',
        'children' => 'integer',
        'keys_left' => 'boolean',
        'paid' => 'boolean',
        'review_request_sent' => 'boolean',
        'cost' => 'decimal:2',
        'quoted_price' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(ParkingCustomer::class, 'customer_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ParkingZone::class, 'zone_id');
    }

    public function parkingSpace(): BelongsTo
    {
        return $this->belongsTo(ParkingSpace::class, 'parking_space_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ParkingReservationImage::class, 'parking_reservation_id');
    }

    public function statusAudits(): HasMany
    {
        return $this->hasMany(ParkingStatusAudit::class, 'parking_reservation_id');
    }
}
```

Create `app/Models/ParkingReservationImage.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingReservationImage extends Model
{
    protected $fillable = ['parking_reservation_id', 'path', 'caption'];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ParkingReservation::class, 'parking_reservation_id');
    }
}
```

Create `app/Models/ParkingStatusAudit.php` (tabelul nu are coloane `created_at`/`updated_at` — doar `changed_at`):

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingStatusAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'parking_reservation_id', 'user_id', 'from_status', 'to_status', 'changed_at', 'notes',
    ];

    protected $casts = ['changed_at' => 'datetime'];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ParkingReservation::class, 'parking_reservation_id');
    }
}
```

- [ ] **Step 4: Rulează testul (trece)**

Run: `docker compose exec -T app php artisan test --filter=ParkingModelsTest`
Așteptat: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models tests/Feature/Panel/ParkingModelsTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(models): add parking Eloquent models

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Modele Eloquent — Cazare

**Files:**
- Create: `app/Models/LodgingProperty.php`, `Room.php`, `LodgingReservation.php`, `LodgingSyncLink.php`
- Test: `tests/Feature/Panel/LodgingModelsTest.php`

- [ ] **Step 1: Scrie testul care eșuează**

Create `tests/Feature/Panel/LodgingModelsTest.php`:

```php
<?php

namespace Tests\Feature\Panel;

use App\Models\LodgingProperty;
use App\Models\LodgingReservation;
use App\Models\LodgingSyncLink;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LodgingModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_room_and_reservation_relations(): void
    {
        $property = LodgingProperty::create(['name' => 'Sky Center', 'is_active' => true]);
        $room = Room::create(['property_id' => $property->id, 'name' => 'Camera 1', 'is_active' => true]);
        $reservation = LodgingReservation::create([
            'room_id' => $room->id,
            'guest_name' => 'Maria Ionescu',
            'status' => 'confirmed',
            'check_in' => '2026-06-10',
            'check_out' => '2026-06-12',
            'nights' => 2,
            'price' => 350.00,
        ]);
        LodgingSyncLink::create([
            'property_id' => $property->id,
            'channel' => 'booking',
            'ical_url' => 'https://example.com/cal.ics',
        ]);

        $this->assertSame(1, $property->rooms()->count());
        $this->assertSame(1, $property->syncLinks()->count());
        $this->assertSame('Sky Center', $room->property->name);
        $this->assertSame('Camera 1', $reservation->room->name);
        $this->assertEquals('2026-06-10', $reservation->check_in->toDateString());
        $this->assertTrue($room->is_active);
    }
}
```

- [ ] **Step 2: Rulează testul (eșuează)**

Run: `docker compose exec -T app php artisan test --filter=LodgingModelsTest`
Așteptat: FAIL — `LodgingProperty` nu există.

- [ ] **Step 3: Creează modelele de cazare**

Create `app/Models/LodgingProperty.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LodgingProperty extends Model
{
    protected $fillable = ['source', 'external_id', 'name', 'slug', 'is_active', 'notes', 'metadata'];

    protected $casts = ['is_active' => 'boolean', 'metadata' => 'array'];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'property_id');
    }

    public function syncLinks(): HasMany
    {
        return $this->hasMany(LodgingSyncLink::class, 'property_id');
    }
}
```

Create `app/Models/Room.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = ['source', 'external_id', 'property_id', 'name', 'is_active', 'notes', 'metadata'];

    protected $casts = ['is_active' => 'boolean', 'metadata' => 'array'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(LodgingProperty::class, 'property_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(LodgingReservation::class, 'room_id');
    }
}
```

Create `app/Models/LodgingReservation.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LodgingReservation extends Model
{
    protected $fillable = [
        'source', 'external_id', 'room_id', 'guest_name', 'phone', 'normalized_phone', 'email',
        'status', 'check_in', 'check_out', 'nights', 'price', 'direct_price', 'currency',
        'source_created_at', 'notes', 'metadata', 'created_by_id', 'updated_by_id',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
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

Create `app/Models/LodgingSyncLink.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LodgingSyncLink extends Model
{
    protected $fillable = ['property_id', 'room_id', 'channel', 'ical_url', 'last_synced_at', 'is_active'];

    protected $casts = ['last_synced_at' => 'datetime', 'is_active' => 'boolean'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(LodgingProperty::class, 'property_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
}
```

- [ ] **Step 4: Rulează testul (trece)**

Run: `docker compose exec -T app php artisan test --filter=LodgingModelsTest`
Așteptat: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models tests/Feature/Panel/LodgingModelsTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(models): add lodging Eloquent models

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Modele Eloquent — Rent-a-car

**Files:**
- Create: `app/Models/RentVehicle.php`, `RentVehicleImage.php`, `RentClient.php`, `RentContract.php`, `RentMaintenanceRecord.php`
- Test: `tests/Feature/Panel/RentModelsTest.php`

- [ ] **Step 1: Scrie testul care eșuează**

Create `tests/Feature/Panel/RentModelsTest.php`:

```php
<?php

namespace Tests\Feature\Panel;

use App\Models\RentClient;
use App\Models\RentContract;
use App\Models\RentMaintenanceRecord;
use App\Models\RentVehicle;
use App\Models\RentVehicleImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_contract_and_relations(): void
    {
        $vehicle = RentVehicle::create([
            'license_plate' => 'B 99 SKY',
            'brand' => 'Dacia',
            'model_name' => 'Logan',
            'manufacture_year' => 2021,
            'status' => 'available',
            'insurance_12_months' => true,
        ]);
        $client = RentClient::create(['name' => 'Andrei Rusu', 'phone' => '0711111111']);
        $contract = RentContract::create([
            'rent_vehicle_id' => $vehicle->id,
            'rent_client_id' => $client->id,
            'usage_type' => 'rent',
            'status' => 'active',
            'start_date' => '2026-06-08',
            'end_date' => '2026-06-15',
            'total_price' => 700.00,
        ]);
        RentVehicleImage::create(['rent_vehicle_id' => $vehicle->id, 'path' => 'img/car.jpg']);
        RentMaintenanceRecord::create(['rent_vehicle_id' => $vehicle->id, 'intervention_type' => 'schimb ulei']);

        $this->assertSame(1, $vehicle->contracts()->count());
        $this->assertSame(1, $vehicle->images()->count());
        $this->assertSame(1, $vehicle->maintenanceRecords()->count());
        $this->assertSame('Dacia', $contract->vehicle->brand);
        $this->assertSame('Andrei Rusu', $contract->client->name);
        $this->assertTrue($vehicle->insurance_12_months);
    }
}
```

- [ ] **Step 2: Rulează testul (eșuează)**

Run: `docker compose exec -T app php artisan test --filter=RentModelsTest`
Așteptat: FAIL — `RentVehicle` nu există.

- [ ] **Step 3: Creează modelele rent-a-car**

Create `app/Models/RentVehicle.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentVehicle extends Model
{
    protected $fillable = [
        'source', 'external_id', 'license_plate', 'chassis_vin', 'brand', 'model_name',
        'manufacture_year', 'tire_type', 'insurance_start_date', 'insurance_end_date',
        'insurance_12_months', 'itp_date', 'itp_expiry_date', 'current_km',
        'monthly_rent_price', 'daily_rent_price', 'warranty_standard', 'currency',
        'status', 'notes', 'metadata',
    ];

    protected $casts = [
        'manufacture_year' => 'integer',
        'insurance_start_date' => 'date',
        'insurance_end_date' => 'date',
        'insurance_12_months' => 'boolean',
        'itp_date' => 'date',
        'itp_expiry_date' => 'date',
        'current_km' => 'integer',
        'monthly_rent_price' => 'decimal:2',
        'daily_rent_price' => 'decimal:2',
        'warranty_standard' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(RentVehicleImage::class, 'rent_vehicle_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(RentContract::class, 'rent_vehicle_id');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(RentMaintenanceRecord::class, 'rent_vehicle_id');
    }
}
```

Create `app/Models/RentVehicleImage.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentVehicleImage extends Model
{
    protected $fillable = ['rent_vehicle_id', 'path', 'caption'];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(RentVehicle::class, 'rent_vehicle_id');
    }
}
```

Create `app/Models/RentClient.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentClient extends Model
{
    protected $fillable = [
        'source', 'external_id', 'name', 'phone', 'normalized_phone', 'email',
        'identity_document', 'notes', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function contracts(): HasMany
    {
        return $this->hasMany(RentContract::class, 'rent_client_id');
    }
}
```

Create `app/Models/RentContract.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentContract extends Model
{
    protected $fillable = [
        'source', 'external_id', 'contract_code', 'rent_vehicle_id', 'rent_client_id',
        'usage_type', 'start_date', 'end_date', 'km_at_handover', 'km_at_return',
        'daily_price', 'monthly_price', 'warranty_collected', 'total_price', 'currency',
        'status', 'notes', 'metadata', 'created_by_id', 'updated_by_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'km_at_handover' => 'integer',
        'km_at_return' => 'integer',
        'daily_price' => 'decimal:2',
        'monthly_price' => 'decimal:2',
        'warranty_collected' => 'decimal:2',
        'total_price' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(RentVehicle::class, 'rent_vehicle_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(RentClient::class, 'rent_client_id');
    }
}
```

Create `app/Models/RentMaintenanceRecord.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentMaintenanceRecord extends Model
{
    protected $fillable = [
        'rent_vehicle_id', 'service_at', 'mileage_at_service', 'intervention_type',
        'next_service_km', 'details', 'metadata',
    ];

    protected $casts = [
        'service_at' => 'datetime',
        'mileage_at_service' => 'integer',
        'next_service_km' => 'integer',
        'metadata' => 'array',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(RentVehicle::class, 'rent_vehicle_id');
    }
}
```

- [ ] **Step 4: Rulează testul (trece)**

Run: `docker compose exec -T app php artisan test --filter=RentModelsTest`
Așteptat: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models tests/Feature/Panel/RentModelsTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(models): add rent-a-car Eloquent models

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Modele Eloquent — Plăți

**Files:**
- Create: `app/Models/Payment.php`, `PaymentChangeAudit.php`
- Test: `tests/Feature/Panel/PaymentModelsTest.php`

- [ ] **Step 1: Scrie testul care eșuează**

Create `tests/Feature/Panel/PaymentModelsTest.php`:

```php
<?php

namespace Tests\Feature\Panel;

use App\Models\LodgingProperty;
use App\Models\LodgingReservation;
use App\Models\Payment;
use App\Models\PaymentChangeAudit;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_links_to_reservation_and_audits(): void
    {
        $property = LodgingProperty::create(['name' => 'Serafim']);
        $room = Room::create(['property_id' => $property->id, 'name' => 'Camera 1']);
        $reservation = LodgingReservation::create(['room_id' => $room->id, 'guest_name' => 'X']);

        $payment = Payment::create([
            'service' => 'lodging',
            'lodging_reservation_id' => $reservation->id,
            'amount' => 350.00,
            'method' => 'cash',
        ]);
        PaymentChangeAudit::create(['payment_id' => $payment->id, 'action' => 'created']);

        $this->assertSame('lodging', $payment->service);
        $this->assertSame($reservation->id, $payment->lodgingReservation->id);
        $this->assertSame(1, $payment->changeAudits()->count());
    }
}
```

- [ ] **Step 2: Rulează testul (eșuează)**

Run: `docker compose exec -T app php artisan test --filter=PaymentModelsTest`
Așteptat: FAIL — `Payment` nu există.

- [ ] **Step 3: Creează modelele de plăți**

Create `app/Models/Payment.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'source', 'external_id', 'service', 'parking_reservation_id', 'lodging_reservation_id',
        'rent_contract_id', 'amount', 'currency', 'method', 'paid_at', 'notes', 'metadata',
        'created_by_id', 'updated_by_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function parkingReservation(): BelongsTo
    {
        return $this->belongsTo(ParkingReservation::class, 'parking_reservation_id');
    }

    public function lodgingReservation(): BelongsTo
    {
        return $this->belongsTo(LodgingReservation::class, 'lodging_reservation_id');
    }

    public function rentContract(): BelongsTo
    {
        return $this->belongsTo(RentContract::class, 'rent_contract_id');
    }

    public function changeAudits(): HasMany
    {
        return $this->hasMany(PaymentChangeAudit::class, 'payment_id');
    }
}
```

Create `app/Models/PaymentChangeAudit.php` (tabelul are doar `created_at`, fără `updated_at`):

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentChangeAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_id', 'user_id', 'action', 'old_values', 'new_values', 'changed_fields', 'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'created_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
```

- [ ] **Step 4: Rulează testul (trece)**

Run: `docker compose exec -T app php artisan test --filter=PaymentModelsTest`
Așteptat: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models tests/Feature/Panel/PaymentModelsTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(models): add payment Eloquent models

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: Modele Eloquent — Buget

**Files:**
- Create: `app/Models/BudgetCategory.php`, `BudgetRawMessage.php`, `BudgetTransaction.php`, `Salary.php`
- Test: `tests/Feature/Panel/BudgetModelsTest.php`

- [ ] **Step 1: Scrie testul care eșuează**

Create `tests/Feature/Panel/BudgetModelsTest.php`:

```php
<?php

namespace Tests\Feature\Panel;

use App\Models\BudgetCategory;
use App\Models\BudgetRawMessage;
use App\Models\BudgetTransaction;
use App\Models\Salary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_category_and_salary_relations(): void
    {
        $category = BudgetCategory::create([
            'service' => 'hotel', 'name' => 'apă', 'kind' => 'expense', 'frequency' => 'monthly',
        ]);
        $raw = BudgetRawMessage::create([
            'chat_id' => '123', 'message_id' => '456', 'text' => 'apa 100 lei',
        ]);
        $transaction = BudgetTransaction::create([
            'type' => 'expense',
            'category_id' => $category->id,
            'raw_message_id' => $raw->id,
            'amount' => 100.00,
            'occurred_on' => '2026-06-08',
        ]);
        $user = User::factory()->create();
        $salary = Salary::create([
            'user_id' => $user->id,
            'amount' => 4000.00,
            'period_month' => '2026-06-01',
            'status' => 'pending',
        ]);

        $this->assertSame('apă', $transaction->category->name);
        $this->assertSame($raw->id, $transaction->rawMessage->id);
        $this->assertSame('expense', $transaction->type);
        $this->assertSame($user->id, $salary->user->id);
    }
}
```

- [ ] **Step 2: Rulează testul (eșuează)**

Run: `docker compose exec -T app php artisan test --filter=BudgetModelsTest`
Așteptat: FAIL — `BudgetCategory` nu există.

- [ ] **Step 3: Creează modelele de buget**

Create `app/Models/BudgetCategory.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetCategory extends Model
{
    protected $fillable = [
        'service', 'name', 'kind', 'frequency', 'default_amount', 'currency', 'is_active', 'metadata',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(BudgetTransaction::class, 'category_id');
    }
}
```

Create `app/Models/BudgetRawMessage.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetRawMessage extends Model
{
    protected $fillable = [
        'chat_id', 'message_id', 'text', 'parsed', 'transaction_id', 'received_at',
    ];

    protected $casts = [
        'parsed' => 'boolean',
        'received_at' => 'datetime',
    ];
}
```

Create `app/Models/BudgetTransaction.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetTransaction extends Model
{
    protected $fillable = [
        'source', 'external_id', 'type', 'category_id', 'service', 'amount', 'currency',
        'occurred_on', 'description', 'telegram_chat', 'raw_message_id', 'metadata', 'created_by_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_on' => 'date',
        'metadata' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class, 'category_id');
    }

    public function rawMessage(): BelongsTo
    {
        return $this->belongsTo(BudgetRawMessage::class, 'raw_message_id');
    }
}
```

Create `app/Models/Salary.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Salary extends Model
{
    protected $fillable = [
        'user_id', 'employee_name', 'amount', 'currency', 'period_month', 'paid_at', 'status', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_month' => 'date',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

- [ ] **Step 4: Rulează testul (trece)**

Run: `docker compose exec -T app php artisan test --filter=BudgetModelsTest`
Așteptat: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models tests/Feature/Panel/BudgetModelsTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(models): add budget Eloquent models

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## Task 7: Modele Eloquent — Mesagerie & Automatizare

**Files:**
- Create: `app/Models/MessageTemplate.php`, `OutboundMessage.php`, `AutomationWebhookLog.php`, `AutomationEvent.php`
- Test: `tests/Feature/Panel/MessagingModelsTest.php`

- [ ] **Step 1: Scrie testul care eșuează**

Create `tests/Feature/Panel/MessagingModelsTest.php`:

```php
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
```

- [ ] **Step 2: Rulează testul (eșuează)**

Run: `docker compose exec -T app php artisan test --filter=MessagingModelsTest`
Așteptat: FAIL — `MessageTemplate` nu există.

- [ ] **Step 3: Creează modelele de mesagerie & automatizare**

Create `app/Models/MessageTemplate.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    protected $fillable = [
        'source', 'external_id', 'template_key', 'service', 'channel', 'locale',
        'label', 'body', 'is_active', 'metadata',
    ];

    protected $casts = ['is_active' => 'boolean', 'metadata' => 'array'];
}
```

Create `app/Models/OutboundMessage.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboundMessage extends Model
{
    protected $fillable = [
        'service', 'reference_id', 'channel', 'template_key', 'payload',
        'scheduled_at', 'sent_at', 'status',
    ];

    protected $casts = [
        'payload' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];
}
```

Create `app/Models/AutomationWebhookLog.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationWebhookLog extends Model
{
    protected $fillable = [
        'endpoint', 'idempotency_key', 'status', 'http_status', 'event_type', 'service',
        'external_id', 'payload', 'response_body', 'error_message', 'received_at', 'processed_at',
    ];

    protected $casts = [
        'http_status' => 'integer',
        'payload' => 'array',
        'response_body' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(AutomationEvent::class, 'webhook_log_id');
    }
}
```

Create `app/Models/AutomationEvent.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationEvent extends Model
{
    protected $fillable = [
        'webhook_log_id', 'event_type', 'service', 'external_id', 'occurred_at', 'status', 'payload',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'payload' => 'array',
    ];

    public function webhookLog(): BelongsTo
    {
        return $this->belongsTo(AutomationWebhookLog::class, 'webhook_log_id');
    }
}
```

- [ ] **Step 4: Rulează testul (trece)**

Run: `docker compose exec -T app php artisan test --filter=MessagingModelsTest`
Așteptat: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models tests/Feature/Panel/MessagingModelsTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(models): add messaging and automation Eloquent models

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## REȚETA RESURSELOR FILAMENT (citește înainte de Task 8)

Toate task-urile 8–12 folosesc același tipar. Pentru fiecare resursă:

1. **Generează** cu introspecție de coloane:
   ```bash
   docker compose exec -T app php artisan make:filament-resource <Model> --generate --no-interaction
   ```
   Asta creează `app/Filament/Resources/<Model>Resource.php` + paginile `List/Create/Edit` în `.../Pages/`. Formularul și tabelul sunt derivate automat din coloanele tabelului.

2. **Personalizează** clasa resursă — adaugă/seta aceste proprietăți statice imediat sub `protected static ?string $model = ...;` (Filament 5.6 API — tipurile sunt exact acestea):
   ```php
   protected static \UnitEnum|string|null $navigationGroup = '<Grup>';
   protected static \BackedEnum|string|null $navigationIcon = '<heroicon>';
   protected static ?string $navigationLabel = '<Etichetă navigare>';
   protected static ?string $modelLabel = '<Singular>';
   protected static ?string $pluralModelLabel = '<Plural>';
   protected static ?string $slug = '<slug>';
   protected static ?int $navigationSort = <n>;
   ```

3. **Înregistrează Relation Managers** (unde e cazul) în metoda `getRelations()` a resursei:
   ```php
   public static function getRelations(): array
   {
       return [
           RelationManagers\ImagesRelationManager::class,
       ];
   }
   ```
   Generează un relation manager cu:
   ```bash
   docker compose exec -T app php artisan make:filament-relation-manager <Model>Resource <relationship> <recordTitleAttribute> --no-interaction
   ```
   ex: `make:filament-relation-manager ParkingReservationResource images path`.

4. **Importuri**: după ce adaugi proprietățile/`getRelations`, asigură-te că `use BackedEnum;`/`use UnitEnum;` NU sunt necesare (sunt clase globale; folosește `\BackedEnum`/`\UnitEnum` cu backslash, ca în exemplul de mai sus, exact cum face Ops). Importă clasele de RelationManager folosite.

5. **Resurse read-only**: pentru jurnale (automatizări) adaugă în clasă:
   ```php
   public static function canCreate(): bool { return false; }
   ```
   și în relation managerele read-only șterge acțiunile de creare/ștergere din `->headerActions([])` / `->actions([...])` (păstrează doar vizualizarea).

**Grupuri de navigare și iconițe** (folosește-le consecvent):
- Parcare → `heroicon-o-truck`
- Cazare → `heroicon-o-building-office-2`
- Rent-a-car → `heroicon-o-key`
- Buget → `heroicon-o-banknotes`
- Sistem → `heroicon-o-cog-6-tooth`

**Tiparul de test smoke** (per resursă): autentifică un admin, accesează paginile index și create și verifică 200. Acest test compilează întreaga schemă de formular și tabel (prinde erorile de API). Slug-urile sunt explicite, deci căile sunt deterministe.

---

## Task 8: Resurse Filament — Parcare

**Files:**
- Create (generate): `app/Filament/Resources/ParkingReservationResource.php` (+ Pages, RelationManagers), `ParkingCustomerResource.php`, `ParkingLotResource.php`, `ParkingPriceResource.php`
- Test: `tests/Feature/Panel/ParkingPanelTest.php`

- [ ] **Step 1: Generează resursele**

```bash
docker compose exec -T app php artisan make:filament-resource ParkingReservation --generate --no-interaction
docker compose exec -T app php artisan make:filament-resource ParkingCustomer --generate --no-interaction
docker compose exec -T app php artisan make:filament-resource ParkingLot --generate --no-interaction
docker compose exec -T app php artisan make:filament-resource ParkingPrice --generate --no-interaction
```

- [ ] **Step 2: Generează relation managerele**

```bash
docker compose exec -T app php artisan make:filament-relation-manager ParkingReservationResource images path --no-interaction
docker compose exec -T app php artisan make:filament-relation-manager ParkingReservationResource statusAudits to_status --no-interaction
docker compose exec -T app php artisan make:filament-relation-manager ParkingLotResource zones code --no-interaction
```

- [ ] **Step 3: Personalizează fiecare resursă**

În `ParkingReservationResource.php`, sub linia `$model`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Parcare';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-truck';
protected static ?string $navigationLabel = 'Rezervări';
protected static ?string $modelLabel = 'rezervare parcare';
protected static ?string $pluralModelLabel = 'rezervări parcare';
protected static ?string $slug = 'parcare-rezervari';
protected static ?int $navigationSort = 1;
```
și `getRelations()`:
```php
public static function getRelations(): array
{
    return [
        RelationManagers\ImagesRelationManager::class,
        RelationManagers\StatusAuditsRelationManager::class,
    ];
}
```

În `ParkingCustomerResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Parcare';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';
protected static ?string $navigationLabel = 'Clienți';
protected static ?string $modelLabel = 'client parcare';
protected static ?string $pluralModelLabel = 'clienți parcare';
protected static ?string $slug = 'parcare-clienti';
protected static ?int $navigationSort = 2;
```

În `ParkingLotResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Parcare';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map';
protected static ?string $navigationLabel = 'Loturi & zone';
protected static ?string $modelLabel = 'lot de parcare';
protected static ?string $pluralModelLabel = 'loturi de parcare';
protected static ?string $slug = 'parcare-loturi';
protected static ?int $navigationSort = 3;
```
și `getRelations()`:
```php
public static function getRelations(): array
{
    return [
        RelationManagers\ZonesRelationManager::class,
    ];
}
```

În `ParkingPriceResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Parcare';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-currency-dollar';
protected static ?string $navigationLabel = 'Prețuri';
protected static ?string $modelLabel = 'preț parcare';
protected static ?string $pluralModelLabel = 'prețuri parcare';
protected static ?string $slug = 'parcare-preturi';
protected static ?int $navigationSort = 4;
```

- [ ] **Step 4: Scrie testul smoke**

Create `tests/Feature/Panel/ParkingPanelTest.php`:

```php
<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    /** @dataProvider parkingSlugs */
    public function test_index_and_create_pages_render(string $slug): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get("/admin/{$slug}")->assertSuccessful();
        $this->actingAs($admin)->get("/admin/{$slug}/create")->assertSuccessful();
    }

    public static function parkingSlugs(): array
    {
        return [
            ['parcare-rezervari'],
            ['parcare-clienti'],
            ['parcare-loturi'],
            ['parcare-preturi'],
        ];
    }
}
```

- [ ] **Step 5: Rulează testul (trece)**

Run: `docker compose exec -T app php artisan test --filter=ParkingPanelTest`
Așteptat: PASS (8 aserțiuni — index + create pentru 4 resurse). Dacă o pagină dă 500, citește eroarea: de obicei o coloană inexistentă în form — verifică maparea cu migrația.

- [ ] **Step 6: Commit**

```bash
git add app/Filament tests/Feature/Panel/ParkingPanelTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(panel): add parking Filament resources

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## Task 9: Resurse Filament — Cazare

**Files:**
- Create (generate): `LodgingPropertyResource`, `RoomResource`, `LodgingReservationResource` (+ Pages, RelationManagers)
- Test: `tests/Feature/Panel/LodgingPanelTest.php`

- [ ] **Step 1: Generează resursele și relation managerul**

```bash
docker compose exec -T app php artisan make:filament-resource LodgingProperty --generate --no-interaction
docker compose exec -T app php artisan make:filament-resource Room --generate --no-interaction
docker compose exec -T app php artisan make:filament-resource LodgingReservation --generate --no-interaction
docker compose exec -T app php artisan make:filament-relation-manager LodgingPropertyResource syncLinks channel --no-interaction
```

- [ ] **Step 2: Personalizează resursele**

`LodgingPropertyResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Cazare';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';
protected static ?string $navigationLabel = 'Proprietăți';
protected static ?string $modelLabel = 'proprietate';
protected static ?string $pluralModelLabel = 'proprietăți';
protected static ?string $slug = 'cazare-proprietati';
protected static ?int $navigationSort = 1;
```
+ `getRelations()` → `[RelationManagers\SyncLinksRelationManager::class]`.

`RoomResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Cazare';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-home';
protected static ?string $navigationLabel = 'Camere';
protected static ?string $modelLabel = 'cameră';
protected static ?string $pluralModelLabel = 'camere';
protected static ?string $slug = 'cazare-camere';
protected static ?int $navigationSort = 2;
```

`LodgingReservationResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Cazare';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';
protected static ?string $navigationLabel = 'Rezervări';
protected static ?string $modelLabel = 'rezervare cazare';
protected static ?string $pluralModelLabel = 'rezervări cazare';
protected static ?string $slug = 'cazare-rezervari';
protected static ?int $navigationSort = 3;
```

- [ ] **Step 3: Scrie testul smoke**

Create `tests/Feature/Panel/LodgingPanelTest.php` (identic ca structură cu ParkingPanelTest, clasa `LodgingPanelTest`, dataProvider `lodgingSlugs` cu: `cazare-proprietati`, `cazare-camere`, `cazare-rezervari`).

- [ ] **Step 4: Rulează testul (trece)**

Run: `docker compose exec -T app php artisan test --filter=LodgingPanelTest`
Așteptat: PASS (6 aserțiuni).

- [ ] **Step 5: Commit**

```bash
git add app/Filament tests/Feature/Panel/LodgingPanelTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(panel): add lodging Filament resources

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## Task 10: Resurse Filament — Rent-a-car

**Files:**
- Create (generate): `RentVehicleResource`, `RentClientResource`, `RentContractResource`, `RentMaintenanceRecordResource` (+ Pages, RelationManagers)
- Test: `tests/Feature/Panel/RentPanelTest.php`

- [ ] **Step 1: Generează resursele și relation managerul**

```bash
docker compose exec -T app php artisan make:filament-resource RentVehicle --generate --no-interaction
docker compose exec -T app php artisan make:filament-resource RentClient --generate --no-interaction
docker compose exec -T app php artisan make:filament-resource RentContract --generate --no-interaction
docker compose exec -T app php artisan make:filament-resource RentMaintenanceRecord --generate --no-interaction
docker compose exec -T app php artisan make:filament-relation-manager RentVehicleResource images path --no-interaction
```

- [ ] **Step 2: Personalizează resursele**

`RentVehicleResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Rent-a-car';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-key';
protected static ?string $navigationLabel = 'Mașini';
protected static ?string $modelLabel = 'mașină';
protected static ?string $pluralModelLabel = 'mașini';
protected static ?string $slug = 'rent-masini';
protected static ?int $navigationSort = 1;
```
+ `getRelations()` → `[RelationManagers\ImagesRelationManager::class]`.

`RentClientResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Rent-a-car';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
protected static ?string $navigationLabel = 'Clienți';
protected static ?string $modelLabel = 'client rent';
protected static ?string $pluralModelLabel = 'clienți rent';
protected static ?string $slug = 'rent-clienti';
protected static ?int $navigationSort = 2;
```

`RentContractResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Rent-a-car';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
protected static ?string $navigationLabel = 'Contracte';
protected static ?string $modelLabel = 'contract';
protected static ?string $pluralModelLabel = 'contracte';
protected static ?string $slug = 'rent-contracte';
protected static ?int $navigationSort = 3;
```

`RentMaintenanceRecordResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Rent-a-car';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-wrench-screwdriver';
protected static ?string $navigationLabel = 'Mentenanță';
protected static ?string $modelLabel = 'înregistrare mentenanță';
protected static ?string $pluralModelLabel = 'mentenanță';
protected static ?string $slug = 'rent-mentenanta';
protected static ?int $navigationSort = 4;
```

- [ ] **Step 3: Scrie testul smoke**

Create `tests/Feature/Panel/RentPanelTest.php` (structură identică, clasa `RentPanelTest`, slugs: `rent-masini`, `rent-clienti`, `rent-contracte`, `rent-mentenanta`).

- [ ] **Step 4: Rulează testul (trece)**

Run: `docker compose exec -T app php artisan test --filter=RentPanelTest`
Așteptat: PASS (8 aserțiuni).

- [ ] **Step 5: Commit**

```bash
git add app/Filament tests/Feature/Panel/RentPanelTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(panel): add rent-a-car Filament resources

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## Task 11: Resurse Filament — Buget

**Files:**
- Create (generate): `BudgetCategoryResource`, `BudgetTransactionResource`, `BudgetRawMessageResource`, `SalaryResource` (+ Pages)
- Test: `tests/Feature/Panel/BudgetPanelTest.php`

> Notă: restricția de acces admin-only pentru grupul Buget se aplică în Task 13. În acest task resursele sunt accesibile oricărui utilizator autentificat; testul smoke folosește un admin.

- [ ] **Step 1: Generează resursele**

```bash
docker compose exec -T app php artisan make:filament-resource BudgetCategory --generate --no-interaction
docker compose exec -T app php artisan make:filament-resource BudgetTransaction --generate --no-interaction
docker compose exec -T app php artisan make:filament-resource BudgetRawMessage --generate --no-interaction
docker compose exec -T app php artisan make:filament-resource Salary --generate --no-interaction
```

- [ ] **Step 2: Personalizează resursele**

`BudgetCategoryResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Buget';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';
protected static ?string $navigationLabel = 'Categorii';
protected static ?string $modelLabel = 'categorie buget';
protected static ?string $pluralModelLabel = 'categorii buget';
protected static ?string $slug = 'buget-categorii';
protected static ?int $navigationSort = 1;
```

`BudgetTransactionResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Buget';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrows-right-left';
protected static ?string $navigationLabel = 'Tranzacții';
protected static ?string $modelLabel = 'tranzacție';
protected static ?string $pluralModelLabel = 'tranzacții';
protected static ?string $slug = 'buget-tranzactii';
protected static ?int $navigationSort = 2;
```

`BudgetRawMessageResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Buget';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
protected static ?string $navigationLabel = 'Mesaje brute';
protected static ?string $modelLabel = 'mesaj brut';
protected static ?string $pluralModelLabel = 'mesaje brute';
protected static ?string $slug = 'buget-mesaje';
protected static ?int $navigationSort = 3;
```

`SalaryResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Buget';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';
protected static ?string $navigationLabel = 'Salarii';
protected static ?string $modelLabel = 'salariu';
protected static ?string $pluralModelLabel = 'salarii';
protected static ?string $slug = 'buget-salarii';
protected static ?int $navigationSort = 4;
```

- [ ] **Step 3: Scrie testul smoke**

Create `tests/Feature/Panel/BudgetPanelTest.php` (structură identică, clasa `BudgetPanelTest`, slugs: `buget-categorii`, `buget-tranzactii`, `buget-mesaje`, `buget-salarii`).

- [ ] **Step 4: Rulează testul (trece)**

Run: `docker compose exec -T app php artisan test --filter=BudgetPanelTest`
Așteptat: PASS (8 aserțiuni).

- [ ] **Step 5: Commit**

```bash
git add app/Filament tests/Feature/Panel/BudgetPanelTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(panel): add budget Filament resources

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## Task 12: Resurse Filament — Sistem (Plăți, Mesagerie, Automatizări, Utilizatori)

**Files:**
- Create (generate): `PaymentResource`, `MessageTemplateResource`, `OutboundMessageResource`, `AutomationWebhookLogResource`, `UserResource` (+ Pages, RelationManagers)
- Test: `tests/Feature/Panel/SystemPanelTest.php`

- [ ] **Step 1: Generează resursele și relation managerele**

```bash
docker compose exec -T app php artisan make:filament-resource Payment --generate --no-interaction
docker compose exec -T app php artisan make:filament-resource MessageTemplate --generate --no-interaction
docker compose exec -T app php artisan make:filament-resource OutboundMessage --generate --no-interaction
docker compose exec -T app php artisan make:filament-resource AutomationWebhookLog --generate --no-interaction
docker compose exec -T app php artisan make:filament-resource User --generate --no-interaction
docker compose exec -T app php artisan make:filament-relation-manager PaymentResource changeAudits action --no-interaction
docker compose exec -T app php artisan make:filament-relation-manager AutomationWebhookLogResource events event_type --no-interaction
```

- [ ] **Step 2: Personalizează resursele**

`PaymentResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Sistem';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';
protected static ?string $navigationLabel = 'Plăți';
protected static ?string $modelLabel = 'plată';
protected static ?string $pluralModelLabel = 'plăți';
protected static ?string $slug = 'sistem-plati';
protected static ?int $navigationSort = 1;
```
+ `getRelations()` → `[RelationManagers\ChangeAuditsRelationManager::class]`.

`MessageTemplateResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Sistem';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-duplicate';
protected static ?string $navigationLabel = 'Șabloane mesaje';
protected static ?string $modelLabel = 'șablon mesaj';
protected static ?string $pluralModelLabel = 'șabloane mesaje';
protected static ?string $slug = 'sistem-sabloane';
protected static ?int $navigationSort = 2;
```

`OutboundMessageResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Sistem';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-paper-airplane';
protected static ?string $navigationLabel = 'Mesaje trimise';
protected static ?string $modelLabel = 'mesaj trimis';
protected static ?string $pluralModelLabel = 'mesaje trimise';
protected static ?string $slug = 'sistem-mesaje-trimise';
protected static ?int $navigationSort = 3;
```

`AutomationWebhookLogResource.php` (read-only jurnal):
```php
protected static \UnitEnum|string|null $navigationGroup = 'Sistem';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bolt';
protected static ?string $navigationLabel = 'Jurnal automatizări';
protected static ?string $modelLabel = 'intrare jurnal';
protected static ?string $pluralModelLabel = 'jurnal automatizări';
protected static ?string $slug = 'sistem-automatizari';
protected static ?int $navigationSort = 4;
```
+ adaugă `public static function canCreate(): bool { return false; }`
+ `getRelations()` → `[RelationManagers\EventsRelationManager::class]`.

`UserResource.php`:
```php
protected static \UnitEnum|string|null $navigationGroup = 'Sistem';
protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-circle';
protected static ?string $navigationLabel = 'Utilizatori';
protected static ?string $modelLabel = 'utilizator';
protected static ?string $pluralModelLabel = 'utilizatori';
protected static ?string $slug = 'sistem-utilizatori';
protected static ?int $navigationSort = 5;
```

- [ ] **Step 3: Ajustează formularul UserResource pentru parolă și rol**

În `UserResource.php`, în metoda `form()`, asigură-te că (generatorul poate omite/încurca aceste câmpuri):
- câmpul `password` folosește `->password()->dehydrated(fn ($state) => filled($state))->dehydrateStateUsing(fn ($state) => \Illuminate\Support\Facades\Hash::make($state))->required(fn (string $operation) => $operation === 'create')` (parola se hash-uiește, e obligatorie doar la creare).
- câmpul `role` e un `Select` cu opțiunile `['admin' => 'Administrator', 'operator' => 'Operator']`.
- câmpul `is_active` e un `Toggle` cu `->default(true)`.

Importă `Filament\Forms\Components\Select`, `Filament\Forms\Components\Toggle`, `Filament\Forms\Components\TextInput` dacă nu sunt deja importate. Elimină din formular câmpurile `email_verified_at` și `remember_token` dacă au fost generate.

- [ ] **Step 4: Scrie testul smoke**

Create `tests/Feature/Panel/SystemPanelTest.php`. Pentru resursele cu CRUD complet testează index+create; pentru `sistem-automatizari` testează doar index (create e dezactivat):

```php
<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    /** @dataProvider crudSlugs */
    public function test_index_and_create_render(string $slug): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get("/admin/{$slug}")->assertSuccessful();
        $this->actingAs($admin)->get("/admin/{$slug}/create")->assertSuccessful();
    }

    public function test_automation_journal_index_renders(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/sistem-automatizari')->assertSuccessful();
    }

    public static function crudSlugs(): array
    {
        return [
            ['sistem-plati'],
            ['sistem-sabloane'],
            ['sistem-mesaje-trimise'],
            ['sistem-utilizatori'],
        ];
    }
}
```

- [ ] **Step 5: Rulează testul (trece)**

Run: `docker compose exec -T app php artisan test --filter=SystemPanelTest`
Așteptat: PASS (9 aserțiuni).

- [ ] **Step 6: Commit**

```bash
git add app/Filament tests/Feature/Panel/SystemPanelTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(panel): add system Filament resources (payments, messaging, automation, users)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## Task 13: Control de acces pe roluri (admin-only pentru Buget, Plăți, Utilizatori)

Restricționează accesul la resursele financiare și de gestionare a utilizatorilor doar la rolul `admin`. Operatorii nu trebuie să le vadă în navigare și nu trebuie să le poată accesa direct prin URL.

**Files:**
- Modify: `BudgetCategoryResource`, `BudgetTransactionResource`, `BudgetRawMessageResource`, `SalaryResource`, `PaymentResource`, `UserResource`
- Test: `tests/Feature/Panel/RoleAccessTest.php`

- [ ] **Step 1: Scrie testul care eșuează**

Create `tests/Feature/Panel/RoleAccessTest.php`:

```php
<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    /** @dataProvider adminOnlySlugs */
    public function test_operator_is_forbidden_on_admin_only_resources(string $slug): void
    {
        $this->actingAs($this->user('operator'))->get("/admin/{$slug}")->assertForbidden();
    }

    /** @dataProvider adminOnlySlugs */
    public function test_admin_can_access_admin_only_resources(string $slug): void
    {
        $this->actingAs($this->user('admin'))->get("/admin/{$slug}")->assertSuccessful();
    }

    public function test_operator_can_access_operational_resources(): void
    {
        $this->actingAs($this->user('operator'))->get('/admin/parcare-rezervari')->assertSuccessful();
        $this->actingAs($this->user('operator'))->get('/admin/cazare-rezervari')->assertSuccessful();
        $this->actingAs($this->user('operator'))->get('/admin/rent-masini')->assertSuccessful();
    }

    public static function adminOnlySlugs(): array
    {
        return [
            ['buget-categorii'],
            ['buget-tranzactii'],
            ['buget-mesaje'],
            ['buget-salarii'],
            ['sistem-plati'],
            ['sistem-utilizatori'],
        ];
    }
}
```

- [ ] **Step 2: Rulează testul (eșuează)**

Run: `docker compose exec -T app php artisan test --filter=RoleAccessTest`
Așteptat: FAIL — operatorul primește 200 în loc de 403 pe resursele admin-only.

- [ ] **Step 3: Adaugă gating-ul pe rol în cele 6 resurse**

În FIECARE dintre `BudgetCategoryResource`, `BudgetTransactionResource`, `BudgetRawMessageResource`, `SalaryResource`, `PaymentResource`, `UserResource`, adaugă această metodă (folosește `Filament\Facades\Filament` — importă-l):

```php
public static function canAccess(): bool
{
    $user = Filament::auth()->user();

    return $user instanceof \App\Models\User && $user->isAdmin();
}
```

`canAccess()` controlează atât vizibilitatea în navigare cât și accesul direct prin URL (returnează 403 dacă e fals). Asigură-te că `use Filament\Facades\Filament;` e prezent în fiecare fișier.

- [ ] **Step 4: Rulează testul (trece)**

Run: `docker compose exec -T app php artisan test --filter=RoleAccessTest`
Așteptat: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Filament tests/Feature/Panel/RoleAccessTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(panel): restrict budget, payments and users to admin role

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## Task 14: Pagina "Ordinea de zi"

Pagină custom Filament, ecran principal după login. Selector de dată (Azi/Mâine/Poimâine + calendar), tab-uri pe servicii, listă de evenimente pe ziua selectată și disponibilitate ca bare de progres.

**Files:**
- Create: `app/Filament/Pages/OrdineaDeZi.php`
- Create: `resources/views/filament/pages/ordinea-de-zi.blade.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php` (setează `homeUrl` la această pagină)
- Test: `tests/Feature/Panel/OrdineaDeZiTest.php`

- [ ] **Step 1: Scrie testul care eșuează**

Create `tests/Feature/Panel/OrdineaDeZiTest.php`:

```php
<?php

namespace Tests\Feature\Panel;

use App\Filament\Pages\OrdineaDeZi;
use App\Models\LodgingProperty;
use App\Models\LodgingReservation;
use App\Models\ParkingLot;
use App\Models\ParkingReservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdineaDeZiTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        return User::factory()->create(['role' => 'operator', 'is_active' => true]);
    }

    public function test_page_renders_for_authenticated_user(): void
    {
        $this->actingAs($this->operator())->get('/admin/ordinea-de-zi')->assertSuccessful();
    }

    public function test_parking_availability_counts_active_reservations(): void
    {
        $lot = ParkingLot::create(['name' => 'Parcarea 1', 'total_spaces' => 54]);
        ParkingReservation::create([
            'lot_id' => $lot->id, 'status' => 'parked',
            'check_in_at' => '2026-06-10 09:00:00', 'check_out_at' => '2026-06-12 09:00:00',
        ]);

        $page = new OrdineaDeZi();
        $page->selectedDate = '2026-06-10';
        $snapshot = $page->getParkingAvailability();

        $this->assertSame(54, $snapshot['totalSpaces']);
        $this->assertSame(1, $snapshot['occupied']);
    }

    public function test_lodging_events_list_check_ins_for_selected_day(): void
    {
        $property = LodgingProperty::create(['name' => 'Sky Center']);
        $room = Room::create(['property_id' => $property->id, 'name' => 'Camera 1', 'is_active' => true]);
        LodgingReservation::create([
            'room_id' => $room->id, 'guest_name' => 'Maria', 'status' => 'confirmed',
            'check_in' => '2026-06-10', 'check_out' => '2026-06-12',
        ]);

        $page = new OrdineaDeZi();
        $page->selectedDate = '2026-06-10';
        $events = $page->getLodgingEvents();

        $this->assertCount(1, $events['checkIns']);
        $this->assertSame('Maria', $events['checkIns'][0]['guest']);
        $this->assertCount(0, $events['checkOuts']);
    }
}
```

- [ ] **Step 2: Rulează testul (eșuează)**

Run: `docker compose exec -T app php artisan test --filter=OrdineaDeZiTest`
Așteptat: FAIL — `OrdineaDeZi` nu există.

- [ ] **Step 3: Creează pagina**

Create `app/Filament/Pages/OrdineaDeZi.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Models\LodgingReservation;
use App\Models\ParkingLot;
use App\Models\ParkingReservation;
use App\Models\RentContract;
use App\Models\RentVehicle;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class OrdineaDeZi extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Ordinea de zi';

    protected static ?int $navigationSort = -100;

    protected static ?string $slug = 'ordinea-de-zi';

    protected string $view = 'filament.pages.ordinea-de-zi';

    private const TZ = 'Europe/Bucharest';

    #[Url(keep: true)]
    public ?string $selectedDate = null;

    public string $activeService = 'parcare';

    public function mount(): void
    {
        $this->selectedDate = $this->normalizeDate($this->selectedDate);
    }

    public function updatedSelectedDate(?string $value): void
    {
        $this->selectedDate = $this->normalizeDate($value);
    }

    public function getTitle(): string
    {
        return 'Ordinea de zi';
    }

    private function normalizeDate(?string $value): string
    {
        try {
            return CarbonImmutable::parse($value ?: 'now', self::TZ)->toDateString();
        } catch (\Throwable) {
            return CarbonImmutable::now(self::TZ)->toDateString();
        }
    }

    private function selectedDay(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->selectedDate ?: 'now', self::TZ)->startOfDay();
    }

    /**
     * @return list<array{label:string,date:string,isActive:bool}>
     */
    public function getDateShortcuts(): array
    {
        $today = CarbonImmutable::now(self::TZ)->startOfDay();

        return [
            ['label' => 'Azi', 'date' => $today->toDateString(), 'isActive' => $this->selectedDate === $today->toDateString()],
            ['label' => 'Mâine', 'date' => $today->addDay()->toDateString(), 'isActive' => $this->selectedDate === $today->addDay()->toDateString()],
            ['label' => 'Poimâine', 'date' => $today->addDays(2)->toDateString(), 'isActive' => $this->selectedDate === $today->addDays(2)->toDateString()],
        ];
    }

    /**
     * Parcare: rezervări active (parked/booked) care intersectează ziua selectată.
     *
     * @return array{totalSpaces:int,occupied:int,zones:list<array{lot:string,occupied:int,total:int}>}
     */
    public function getParkingAvailability(): array
    {
        $day = $this->selectedDay();
        $dayEnd = $day->endOfDay();

        $totalSpaces = (int) ParkingLot::query()->sum('total_spaces');

        $occupied = ParkingReservation::query()
            ->whereIn('status', ['booked', 'parked'])
            ->where(function ($q) use ($dayEnd): void {
                $q->whereNull('check_in_at')->orWhere('check_in_at', '<=', $dayEnd);
            })
            ->where(function ($q) use ($day): void {
                $q->whereNull('check_out_at')->orWhere('check_out_at', '>=', $day);
            })
            ->count();

        $zones = ParkingLot::query()
            ->withCount(['reservations as occupied_count' => function ($q) use ($day, $dayEnd): void {
                $q->whereIn('status', ['booked', 'parked'])
                    ->where(function ($qq) use ($dayEnd): void {
                        $qq->whereNull('check_in_at')->orWhere('check_in_at', '<=', $dayEnd);
                    })
                    ->where(function ($qq) use ($day): void {
                        $qq->whereNull('check_out_at')->orWhere('check_out_at', '>=', $day);
                    });
            }])
            ->get()
            ->map(fn (ParkingLot $lot): array => [
                'lot' => $lot->name,
                'occupied' => (int) $lot->occupied_count,
                'total' => (int) $lot->total_spaces,
            ])
            ->all();

        return ['totalSpaces' => $totalSpaces, 'occupied' => $occupied, 'zones' => $zones];
    }

    /**
     * @return array{checkIns:list<array{guest:string,room:string,status:string}>,checkOuts:list<array{guest:string,room:string,status:string}>}
     */
    public function getLodgingEvents(): array
    {
        $date = $this->selectedDay()->toDateString();

        $map = fn (LodgingReservation $r): array => [
            'guest' => $r->guest_name ?? 'fără nume',
            'room' => $r->room?->name ?? 'fără cameră',
            'status' => $r->status ?? '-',
        ];

        return [
            'checkIns' => LodgingReservation::query()->with('room')
                ->whereDate('check_in', $date)->get()->map($map)->values()->all(),
            'checkOuts' => LodgingReservation::query()->with('room')
                ->whereDate('check_out', $date)->get()->map($map)->values()->all(),
        ];
    }

    /**
     * @return array{occupiedRooms:int,totalRooms:int}
     */
    public function getLodgingAvailability(): array
    {
        $date = $this->selectedDay()->toDateString();

        $occupied = LodgingReservation::query()
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->whereDate('check_in', '<=', $date)
            ->whereDate('check_out', '>', $date)
            ->distinct('room_id')
            ->count('room_id');

        return [
            'occupiedRooms' => $occupied,
            'totalRooms' => (int) Room::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * @return array{checkIns:list<array{client:string,vehicle:string,status:string}>,checkOuts:list<array{client:string,vehicle:string,status:string}>,available:int,total:int}
     */
    public function getRentEvents(): array
    {
        $date = $this->selectedDay()->toDateString();

        $map = fn (RentContract $c): array => [
            'client' => $c->client?->name ?? 'fără client',
            'vehicle' => $c->vehicle?->license_plate ?? ($c->vehicle?->brand ?? 'fără mașină'),
            'status' => $c->status ?? '-',
        ];

        return [
            'checkIns' => RentContract::query()->with(['client', 'vehicle'])
                ->whereDate('start_date', $date)->get()->map($map)->values()->all(),
            'checkOuts' => RentContract::query()->with(['client', 'vehicle'])
                ->whereDate('end_date', $date)->get()->map($map)->values()->all(),
            'available' => (int) RentVehicle::query()->where('status', 'available')->count(),
            'total' => (int) RentVehicle::query()->count(),
        ];
    }

    /**
     * @return array{checkIns:list<array{label:string,detail:string,status:string}>,checkOuts:list<array{label:string,detail:string,status:string}>}
     */
    public function getParkingEvents(): array
    {
        $date = $this->selectedDay()->toDateString();

        $map = fn (ParkingReservation $r): array => [
            'label' => $r->plate ?? 'fără număr',
            'detail' => $r->customer?->name ?? 'fără client',
            'status' => $r->status ?? '-',
        ];

        return [
            'checkIns' => ParkingReservation::query()->with('customer')
                ->whereDate('check_in_at', $date)->get()->map($map)->values()->all(),
            'checkOuts' => ParkingReservation::query()->with('customer')
                ->whereDate('check_out_at', $date)->get()->map($map)->values()->all(),
        ];
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof \App\Models\User && $user->canAccessPanel(Filament::getCurrentPanel());
    }
}
```

- [ ] **Step 4: Creează view-ul Blade**

Create `resources/views/filament/pages/ordinea-de-zi.blade.php`:

```blade
<x-filament-panels::page>
    @php
        $parking = $this->getParkingAvailability();
        $parkingEvents = $this->getParkingEvents();
        $lodging = $this->getLodgingAvailability();
        $lodgingEvents = $this->getLodgingEvents();
        $rent = $this->getRentEvents();
    @endphp

    {{-- Selector de dată --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex gap-2">
            @foreach ($this->getDateShortcuts() as $shortcut)
                <button type="button" wire:click="$set('selectedDate', '{{ $shortcut['date'] }}')"
                    @class([
                        'fi-btn fi-btn-size-md rounded-lg px-3 py-2 text-sm font-medium',
                        'bg-primary-600 text-white' => $shortcut['isActive'],
                        'bg-gray-100 dark:bg-gray-800' => ! $shortcut['isActive'],
                    ])>
                    {{ $shortcut['label'] }}
                </button>
            @endforeach
        </div>
        <input type="date" wire:model.live="selectedDate"
            class="fi-input rounded-lg border-gray-300 dark:bg-gray-900" />
        <span class="text-sm text-gray-500">Ziua selectată: <strong>{{ $this->selectedDate }}</strong></span>
    </div>

    {{-- Tab-uri pe servicii --}}
    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700 mb-4">
        @foreach (['parcare' => 'Parcare', 'cazare' => 'Cazare', 'rent' => 'Rent-a-car'] as $key => $label)
            <button type="button" wire:click="$set('activeService', '{{ $key }}')"
                @class([
                    'px-4 py-2 text-sm font-medium border-b-2 -mb-px',
                    'border-primary-600 text-primary-600' => $activeService === $key,
                    'border-transparent text-gray-500' => $activeService !== $key,
                ])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($activeService === 'parcare')
        <x-filament::section heading="Disponibilitate parcare">
            <div class="mb-3 text-sm">Total ocupate: <strong>{{ $parking['occupied'] }}</strong> / {{ $parking['totalSpaces'] }} locuri</div>
            @foreach ($parking['zones'] as $zone)
                @php $pct = $zone['total'] > 0 ? min(100, round($zone['occupied'] / $zone['total'] * 100)) : 0; @endphp
                <div class="mb-2">
                    <div class="flex justify-between text-sm"><span>{{ $zone['lot'] }}</span><span>{{ $zone['occupied'] }} / {{ $zone['total'] }}</span></div>
                    <div class="h-2 rounded bg-gray-200 dark:bg-gray-700">
                        <div class="h-2 rounded bg-primary-600" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endforeach
        </x-filament::section>

        <x-filament::section heading="Evenimente parcare ({{ $this->selectedDate }})" class="mt-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h4 class="font-semibold mb-2">Check-in</h4>
                    @forelse ($parkingEvents['checkIns'] as $e)
                        <div class="text-sm py-1 border-b">{{ $e['label'] }} — {{ $e['detail'] }} <span class="text-gray-400">({{ $e['status'] }})</span></div>
                    @empty
                        <div class="text-sm text-gray-400">Niciun check-in.</div>
                    @endforelse
                </div>
                <div>
                    <h4 class="font-semibold mb-2">Check-out</h4>
                    @forelse ($parkingEvents['checkOuts'] as $e)
                        <div class="text-sm py-1 border-b">{{ $e['label'] }} — {{ $e['detail'] }} <span class="text-gray-400">({{ $e['status'] }})</span></div>
                    @empty
                        <div class="text-sm text-gray-400">Niciun check-out.</div>
                    @endforelse
                </div>
            </div>
        </x-filament::section>
    @elseif ($activeService === 'cazare')
        <x-filament::section heading="Disponibilitate cazare">
            <div class="text-sm">Camere ocupate: <strong>{{ $lodging['occupiedRooms'] }}</strong> / {{ $lodging['totalRooms'] }}</div>
        </x-filament::section>
        <x-filament::section heading="Sosiri & plecări cazare ({{ $this->selectedDate }})" class="mt-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h4 class="font-semibold mb-2">Sosiri (check-in)</h4>
                    @forelse ($lodgingEvents['checkIns'] as $e)
                        <div class="text-sm py-1 border-b">{{ $e['guest'] }} — {{ $e['room'] }} <span class="text-gray-400">({{ $e['status'] }})</span></div>
                    @empty
                        <div class="text-sm text-gray-400">Nicio sosire.</div>
                    @endforelse
                </div>
                <div>
                    <h4 class="font-semibold mb-2">Plecări (check-out)</h4>
                    @forelse ($lodgingEvents['checkOuts'] as $e)
                        <div class="text-sm py-1 border-b">{{ $e['guest'] }} — {{ $e['room'] }} <span class="text-gray-400">({{ $e['status'] }})</span></div>
                    @empty
                        <div class="text-sm text-gray-400">Nicio plecare.</div>
                    @endforelse
                </div>
            </div>
        </x-filament::section>
    @else
        <x-filament::section heading="Disponibilitate rent-a-car">
            <div class="text-sm">Mașini disponibile: <strong>{{ $rent['available'] }}</strong> / {{ $rent['total'] }}</div>
        </x-filament::section>
        <x-filament::section heading="Preluări & predări ({{ $this->selectedDate }})" class="mt-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h4 class="font-semibold mb-2">Preluări (start contract)</h4>
                    @forelse ($rent['checkIns'] as $e)
                        <div class="text-sm py-1 border-b">{{ $e['client'] }} — {{ $e['vehicle'] }} <span class="text-gray-400">({{ $e['status'] }})</span></div>
                    @empty
                        <div class="text-sm text-gray-400">Nicio preluare.</div>
                    @endforelse
                </div>
                <div>
                    <h4 class="font-semibold mb-2">Predări (final contract)</h4>
                    @forelse ($rent['checkOuts'] as $e)
                        <div class="text-sm py-1 border-b">{{ $e['client'] }} — {{ $e['vehicle'] }} <span class="text-gray-400">({{ $e['status'] }})</span></div>
                    @empty
                        <div class="text-sm text-gray-400">Nicio predare.</div>
                    @endforelse
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
```

- [ ] **Step 5: Setează pagina ca ecran principal**

În `app/Providers/Filament/AdminPanelProvider.php`, în lanțul `->panel(...)`, adaugă după `->login()`:
```php
->homeUrl(fn (): string => \App\Filament\Pages\OrdineaDeZi::getUrl(panel: 'admin'))
```
Pagina e descoperită automat prin `->discoverPages(...)` (deja prezent din `filament:install`), deci nu trebuie înregistrată manual.

- [ ] **Step 6: Rulează testul (trece)**

Run: `docker compose exec -T app php artisan test --filter=OrdineaDeZiTest`
Așteptat: PASS (3 teste).

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Pages resources/views/filament app/Providers/Filament tests/Feature/Panel/OrdineaDeZiTest.php
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
feat(panel): add Ordinea de zi page as panel home

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## Task 15: Verificare full-stack

**Files:** niciunul (doar verificare)

- [ ] **Step 1: Reconstruiește baza de date cu seed**

Run:
```bash
docker compose exec -T app php artisan migrate:fresh --seed --force
```
Așteptat: toate migrațiile + seederele rulează, output `DONE`, fără erori. Se creează utilizatorul `admin@skycenter.local`.

- [ ] **Step 2: Rulează întreaga suită de teste**

Run:
```bash
docker compose exec -T app php artisan test
```
Așteptat: TOATE trec — testele de schemă din subproiectul #1 (Common 5, Parking 3, Lodging 3, Rent 3, Payment 2, Budget 3, Seeder 2) + noile teste din acest subproiect (PanelAccess 5, modele: Parking/Lodging/Rent/Payment/Budget/Messaging, panel: Parking/Lodging/Rent/Budget/System, RoleAccess, OrdineaDeZi) + cele 2 default. Zero eșecuri.

- [ ] **Step 3: Verifică ruta panoului și navigarea**

Run:
```bash
docker compose exec -T app php artisan route:list --path=admin
```
Așteptat: rute pentru `/admin/login`, `/admin/ordinea-de-zi`, și pentru toate slug-urile de resurse (`parcare-*`, `cazare-*`, `rent-*`, `buget-*`, `sistem-*`).

- [ ] **Step 4: Verifică smoke manual al paginii principale ca admin (opțional, dacă mediul permite UI)**

Pornește serverul (dacă nu rulează) și accesează `http://localhost:8080/admin` — autentifică-te cu `admin@skycenter.local` / `schimba-parola`, confirmă că "Ordinea de zi" e ecranul principal și că grupurile de navigare apar corect.

- [ ] **Step 5: Commit final de verificare**

```bash
git add -A
git -c user.name="Sky Center" -c user.email="infinitive.gen@gmail.com" commit -m "$(cat <<'EOF'
test: verify full web app panel build

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)" --allow-empty
```

---

## Note de auto-review (deja aplicate)

- **Acoperire spec:** arhitectura/stack (T1), structura panoului & navigarea grupată (T8–T12), relation managers pentru tabelele copil (T8 imagini/status/zone, T9 sync-links, T10 imagini, T12 audit-uri plată/evenimente automatizare), pagina "Ordinea de zi" cu selector dată + tab-uri + bare de progres (T14), roluri admin/operator cu Buget/Plăți/Utilizatori admin-only (T1 + T13), CRUD complet pe toate domeniile inclusiv buget (T11) și mesagerie (T12).
- **Diferențe de schemă față de Ops:** folosim numele reale ale coloanelor noastre (`check_in_at`/`check_out_at` parcare, `check_in`/`check_out` cazare/contracte, `zone_id`, `total_spaces` pe lot). Nicio interogare nu e copiată din Ops.
- **În afara scopului (conform spec):** integrare n8n live (#3), bot Telegram (#4), orar Excel (#5), marketing (#6), Android (#7). Tabelele `automation_*` au doar UI read-only.
- **Risc cunoscut:** generatorul `--generate` poate include în formulare coloane tehnice (`source`, `external_id`, `metadata`, FK-uri de audit). Pentru un panou intern e acceptabil; rafinarea etichetelor/ascunderea câmpurilor se poate face incremental după ce panoul funcționează. Testele smoke (GET index+create) garantează că schemele de formular/tabel compilează corect.

