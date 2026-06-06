# Sky Center Database Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the PostgreSQL 16 database foundation (Laravel 12 migrations + seeders) for the Sky Center internal app, covering parking, lodging, rent-a-car, budget and shared tables.

**Architecture:** Fresh Laravel 12 project running in Docker (PHP 8.3-cli + `postgres:16-alpine`), mirroring the neighbouring `Ops` project's container layout. This sub-project ships ONLY the schema: migrations create all ~24 tables with CHECK constraints and indexes; seeders load fixed reference data (parking lots/zones, lodging properties/rooms, message templates, budget categories). No Eloquent models and no Filament UI yet — those belong to sub-project #2 (web app). Schema is verified with PHPUnit feature tests that assert columns exist and that CHECK constraints reject invalid values.

**Tech Stack:** Laravel 12, PHP 8.3, PostgreSQL 16, Docker Compose, PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-06-06-skycenter-database-design.md`

---

## File Structure

```
App/
  docker/app/Dockerfile            # PHP 8.3-cli + pdo_pgsql + composer
  docker-compose.yml               # app + pgsql services (mirrors Ops)
  .env.example / .env              # DB_CONNECTION=pgsql ...
  database/migrations/
    0001_01_01_000000_create_users_table.php        # MODIFIED (add role/phone/is_active)
    2026_06_06_100000_create_messaging_tables.php    # message_templates, outbound_messages
    2026_06_06_100100_create_automation_tables.php   # automation_webhook_logs, automation_events
    2026_06_06_110000_create_parking_tables.php      # 8 parking tables
    2026_06_06_120000_create_lodging_tables.php      # 4 lodging tables
    2026_06_06_130000_create_rent_tables.php         # 5 rent tables
    2026_06_06_140000_create_payment_tables.php      # payments, payment_change_audits
    2026_06_06_150000_create_budget_tables.php       # 4 budget tables
  database/seeders/
    DatabaseSeeder.php             # MODIFIED to call the seeders below
    ParkingReferenceSeeder.php     # lots + zones
    LodgingReferenceSeeder.php     # properties + rooms
    MessageTemplateSeeder.php      # a few base templates
    BudgetCategorySeeder.php       # standard categories
  tests/Feature/Schema/
    CommonSchemaTest.php
    ParkingSchemaTest.php
    LodgingSchemaTest.php
    RentSchemaTest.php
    PaymentSchemaTest.php
    BudgetSchemaTest.php
    SeederTest.php
  phpunit.xml                      # MODIFIED: pgsql test DB
```

**Migration-convention note (applies to every data table below):** each table has
`source VARCHAR(64) NOT NULL DEFAULT 'manual'`, `external_id VARCHAR(190) NULL`, a unique
`(source, external_id)` index where the table is fed by n8n, `metadata JSONB NULL`, and
`timestampsTz()`. Audit columns `created_by_id` / `updated_by_id` reference `users`. CHECK
constraints are added via `DB::statement(...)` right after `Schema::create(...)`, because
Laravel has no fluent CHECK builder.

---

## Task 1: Scaffold Laravel + Docker + PostgreSQL

**Files:**
- Create: `docker/app/Dockerfile`
- Create: `docker-compose.yml`
- Create: Laravel 12 skeleton (whole project)
- Modify: `.env`

- [ ] **Step 1: Create the app Dockerfile**

Create `docker/app/Dockerfile`:

```dockerfile
FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y libpq-dev unzip git \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
```

- [ ] **Step 2: Create docker-compose.yml**

Create `docker-compose.yml`:

```yaml
services:
  app:
    build:
      context: .
      dockerfile: docker/app/Dockerfile
    working_dir: /var/www/html
    environment:
      APP_ENV: local
      APP_URL: http://localhost:${APP_PORT:-8080}
      DB_CONNECTION: pgsql
      DB_HOST: pgsql
      DB_PORT: 5432
      DB_DATABASE: ${DB_DATABASE:-skycenter_app}
      DB_USERNAME: ${DB_USERNAME:-skycenter}
      DB_PASSWORD: ${DB_PASSWORD:-secret}
    ports:
      - "${APP_PORT:-8080}:8000"
    volumes:
      - .:/var/www/html
    depends_on:
      pgsql:
        condition: service_healthy

  pgsql:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: ${DB_DATABASE:-skycenter_app}
      POSTGRES_USER: ${DB_USERNAME:-skycenter}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-secret}
    ports:
      - "${FORWARD_DB_PORT:-55433}:5432"
    volumes:
      - pgsql-data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-skycenter} -d ${DB_DATABASE:-skycenter_app}"]
      interval: 5s
      timeout: 5s
      retries: 10

volumes:
  pgsql-data:
```

- [ ] **Step 3: Scaffold Laravel 12 into the repo**

The project root already contains `docs/`, `.gitignore`, `docker/`. Scaffold into a temp dir, then move the Laravel skeleton in without clobbering existing files:

```bash
docker run --rm -v "${PWD}:/work" -w /work composer:2 \
  create-project laravel/laravel laravel-skeleton "^12.0" --no-interaction --prefer-dist
# copy skeleton over project root, keeping our docs/.gitignore/docker
cp -rn laravel-skeleton/. ./
rm -rf laravel-skeleton
```

Expected: `artisan`, `composer.json`, `app/`, `database/`, `routes/` now exist at the project root.

- [ ] **Step 4: Configure the environment for PostgreSQL**

Edit `.env` (and mirror the same keys in `.env.example`) so the DB block reads:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=skycenter_app
DB_USERNAME=skycenter
DB_PASSWORD=secret
```

- [ ] **Step 5: Build and start the stack**

Run:
```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
```
Expected: containers `app` and `pgsql` running; `pgsql` healthy.

- [ ] **Step 6: Verify default migrations run on Postgres**

Run:
```bash
docker compose exec app php artisan migrate --force
```
Expected: Laravel's default migrations (`users`, `cache`, `jobs`) run successfully against PostgreSQL, output ends with `DONE`.

- [ ] **Step 7: Create the test database and point phpunit at it**

Run:
```bash
docker compose exec pgsql psql -U skycenter -d skycenter_app -c "CREATE DATABASE skycenter_app_test;"
```

In `phpunit.xml`, inside `<php>`, set (replace any sqlite defaults):

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_HOST" value="pgsql"/>
<env name="DB_PORT" value="5432"/>
<env name="DB_DATABASE" value="skycenter_app_test"/>
<env name="DB_USERNAME" value="skycenter"/>
<env name="DB_PASSWORD" value="secret"/>
```

- [ ] **Step 8: Verify the test runner works**

Run:
```bash
docker compose exec app php artisan test
```
Expected: the default Laravel example tests PASS against the Postgres test DB.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "chore: scaffold Laravel 12 + Docker + PostgreSQL"
```

---

## Task 2: Extend the users table

**Files:**
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`
- Test: `tests/Feature/Schema/CommonSchemaTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Schema/CommonSchemaTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommonSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_operational_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('users', ['phone', 'role', 'is_active']));
    }

    public function test_users_role_check_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('users')->insert([
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'x',
            'role' => 'wizard', 'is_active' => true,
        ]);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker compose exec app php artisan test --filter=CommonSchemaTest`
Expected: FAIL — column `role` does not exist.

- [ ] **Step 3: Modify the users migration**

In `0001_01_01_000000_create_users_table.php`, inside the `users` `Schema::create` closure, after `$table->string('name');` add:

```php
$table->string('phone', 64)->nullable();
$table->string('role', 32)->default('operator');
$table->boolean('is_active')->default(true);
```

Then immediately after the `Schema::create('users', ...)` call (still in `up()`), add:

```php
DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin','operator'))");
```

Add `use Illuminate\Support\Facades\DB;` at the top of the file.

- [ ] **Step 4: Run it to verify it passes**

Run: `docker compose exec app php artisan test --filter=CommonSchemaTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/0001_01_01_000000_create_users_table.php tests/Feature/Schema/CommonSchemaTest.php
git commit -m "feat(db): add operational columns to users"
```

---

## Task 3: Messaging & automation tables

**Files:**
- Create: `database/migrations/2026_06_06_100000_create_messaging_tables.php`
- Create: `database/migrations/2026_06_06_100100_create_automation_tables.php`
- Test: `tests/Feature/Schema/CommonSchemaTest.php` (extend)

- [ ] **Step 1: Add failing tests**

Append these methods to `CommonSchemaTest`:

```php
public function test_messaging_tables_exist(): void
{
    $this->assertTrue(Schema::hasColumns('message_templates', ['template_key', 'channel', 'body', 'is_active']));
    $this->assertTrue(Schema::hasColumns('outbound_messages', ['channel', 'scheduled_at', 'status']));
}

public function test_outbound_message_status_check_rejects_invalid_value(): void
{
    $this->expectException(\Illuminate\Database\QueryException::class);
    DB::table('outbound_messages')->insert([
        'service' => 'parking', 'channel' => 'whatsapp', 'scheduled_at' => now(), 'status' => 'exploded',
    ]);
}

public function test_automation_tables_exist(): void
{
    $this->assertTrue(Schema::hasColumns('automation_webhook_logs', ['endpoint', 'status', 'payload']));
    $this->assertTrue(Schema::hasColumns('automation_events', ['event_type', 'status', 'payload']));
}
```

- [ ] **Step 2: Run to verify failure**

Run: `docker compose exec app php artisan test --filter=CommonSchemaTest`
Expected: FAIL — `message_templates` does not exist.

- [ ] **Step 3: Create the messaging migration**

Create `database/migrations/2026_06_06_100000_create_messaging_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('template_key', 190);
            $table->string('service', 64)->nullable();
            $table->string('channel', 64);
            $table->string('locale', 16)->default('ro');
            $table->string('label')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['source', 'external_id'], 'message_templates_source_external_unique');
            $table->index(['service', 'channel'], 'message_templates_service_channel_index');
            $table->index(['channel', 'locale'], 'message_templates_channel_locale_index');
        });
        DB::statement("ALTER TABLE message_templates ADD CONSTRAINT message_templates_channel_check CHECK (channel IN ('whatsapp','telegram','viber','email','sms'))");

        Schema::create('outbound_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('service', 32);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('channel', 64);
            $table->string('template_key', 190)->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestampTz('scheduled_at');
            $table->timestampTz('sent_at')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestampsTz();

            $table->index(['status', 'scheduled_at'], 'outbound_messages_status_scheduled_index');
        });
        DB::statement("ALTER TABLE outbound_messages ADD CONSTRAINT outbound_messages_status_check CHECK (status IN ('pending','sent','failed','cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
        Schema::dropIfExists('message_templates');
    }
};
```

- [ ] **Step 4: Create the automation migration**

Create `database/migrations/2026_06_06_100100_create_automation_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_webhook_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('endpoint', 255);
            $table->string('idempotency_key', 191)->nullable();
            $table->string('status', 32);
            $table->unsignedSmallInteger('http_status')->default(0);
            $table->string('event_type', 120)->nullable();
            $table->string('service', 32)->nullable();
            $table->string('external_id', 190)->nullable();
            $table->jsonb('payload')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->string('error_message', 255)->nullable();
            $table->timestampTz('received_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->index(['endpoint', 'idempotency_key'], 'automation_webhook_logs_endpoint_idem_index');
            $table->index(['status', 'received_at'], 'automation_webhook_logs_status_received_index');
        });

        Schema::create('automation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_log_id')->nullable()->constrained('automation_webhook_logs')->nullOnDelete();
            $table->string('event_type', 120);
            $table->string('service', 32)->nullable();
            $table->string('external_id', 190)->nullable();
            $table->timestampTz('occurred_at')->nullable();
            $table->string('status', 32)->default('received');
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();

            $table->index(['service', 'event_type'], 'automation_events_service_event_index');
            $table->index('occurred_at', 'automation_events_occurred_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_events');
        Schema::dropIfExists('automation_webhook_logs');
    }
};
```

- [ ] **Step 5: Run to verify pass**

Run: `docker compose exec app php artisan test --filter=CommonSchemaTest`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_06_1000*_create_*_tables.php tests/Feature/Schema/CommonSchemaTest.php
git commit -m "feat(db): add messaging and automation tables"
```

---

## Task 4: Parking tables

**Files:**
- Create: `database/migrations/2026_06_06_110000_create_parking_tables.php`
- Test: `tests/Feature/Schema/ParkingSchemaTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Schema/ParkingSchemaTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParkingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_parking_tables_exist(): void
    {
        foreach (['parking_lots', 'parking_zones', 'parking_spaces', 'parking_customers',
                  'parking_prices', 'parking_reservations', 'parking_reservation_images',
                  'parking_status_audits'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing $table");
        }
        $this->assertTrue(Schema::hasColumns('parking_reservations', [
            'status', 'plate', 'normalized_plate', 'vehicle_type', 'check_in_at',
            'check_out_at', 'days', 'adults', 'children', 'keys_left', 'cost',
            'quoted_price', 'currency', 'review_request_sent',
        ]));
    }

    public function test_reservation_status_check_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('parking_reservations')->insert(['status' => 'flying']);
    }

    public function test_reservation_accepts_valid_status(): void
    {
        $id = DB::table('parking_reservations')->insertGetId([
            'status' => 'pending_approval', 'plate' => 'B123XYZ',
            'vehicle_type' => 'autoturism', 'keys_left' => true,
        ]);
        $this->assertDatabaseHas('parking_reservations', ['id' => $id, 'status' => 'pending_approval']);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `docker compose exec app php artisan test --filter=ParkingSchemaTest`
Expected: FAIL — `parking_lots` missing.

- [ ] **Step 3: Create the parking migration**

Create `database/migrations/2026_06_06_110000_create_parking_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_lots', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 128);
            $table->integer('total_spaces')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        Schema::create('parking_zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lot_id')->constrained('parking_lots')->cascadeOnDelete();
            $table->string('code', 16);
            $table->integer('capacity')->default(0);
            $table->timestampsTz();
            $table->unique(['lot_id', 'code'], 'parking_zones_lot_code_unique');
        });

        Schema::create('parking_customers', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('name')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('normalized_phone', 64)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('city')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'parking_customers_source_external_unique');
        });

        Schema::create('parking_spaces', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->foreignId('lot_id')->constrained('parking_lots')->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('parking_zones')->nullOnDelete();
            $table->string('label', 64)->nullable();
            $table->boolean('requires_keys')->default(false);
            $table->string('vehicle_type_suitability', 128)->nullable();
            $table->unsignedBigInteger('blocks_space_id')->nullable();
            $table->unsignedBigInteger('blocked_by_space_id')->nullable();
            $table->text('xy_map_location')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'parking_spaces_source_external_unique');
        });

        Schema::create('parking_prices', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('vehicle_type', 64)->index();
            $table->integer('min_days')->nullable();
            $table->integer('max_days')->nullable();
            $table->decimal('price_per_day', 10, 2)->nullable();
            $table->decimal('fixed_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'parking_prices_source_external_unique');
        });
        DB::statement("ALTER TABLE parking_prices ADD CONSTRAINT parking_prices_vehicle_type_check CHECK (vehicle_type IN ('autoturism','SUV','dubă'))");

        Schema::create('parking_reservations', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('parking_customers')->nullOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('parking_lots')->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('parking_zones')->nullOnDelete();
            $table->foreignId('parking_space_id')->nullable()->constrained('parking_spaces')->nullOnDelete();
            $table->string('status', 32)->default('pending_approval')->index();
            $table->string('plate', 64)->nullable();
            $table->string('normalized_plate', 64)->nullable()->index();
            $table->string('vehicle_type', 64)->nullable();
            $table->timestampTz('check_in_at')->nullable()->index();
            $table->timestampTz('check_out_at')->nullable()->index();
            $table->decimal('days', 6, 2)->nullable();
            $table->integer('adults')->nullable();
            $table->integer('children')->nullable();
            $table->boolean('keys_left')->default(false);
            $table->decimal('cost', 12, 2)->nullable();
            $table->decimal('quoted_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->boolean('paid')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('review_request_sent')->default(false);
            $table->timestampTz('source_created_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'parking_reservations_source_external_unique');
            $table->index(['customer_id', 'status'], 'parking_reservations_customer_status_index');
            $table->index(['status', 'check_in_at'], 'parking_reservations_status_checkin_index');
            $table->index(['status', 'check_out_at'], 'parking_reservations_status_checkout_index');
            $table->index(['vehicle_type'], 'parking_reservations_vehicle_type_index');
        });
        DB::statement("ALTER TABLE parking_reservations ADD CONSTRAINT parking_reservations_status_check CHECK (status IN ('pending_approval','booked','parked','departed','cancelled'))");
        DB::statement("ALTER TABLE parking_reservations ADD CONSTRAINT parking_reservations_vehicle_type_check CHECK (vehicle_type IS NULL OR vehicle_type IN ('autoturism','SUV','dubă'))");

        Schema::create('parking_reservation_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parking_reservation_id')->constrained('parking_reservations')->cascadeOnDelete();
            $table->text('path');
            $table->string('caption')->nullable();
            $table->timestampsTz();
        });

        Schema::create('parking_status_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parking_reservation_id')->constrained('parking_reservations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->timestampTz('changed_at')->useCurrent();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_status_audits');
        Schema::dropIfExists('parking_reservation_images');
        Schema::dropIfExists('parking_reservations');
        Schema::dropIfExists('parking_prices');
        Schema::dropIfExists('parking_spaces');
        Schema::dropIfExists('parking_customers');
        Schema::dropIfExists('parking_zones');
        Schema::dropIfExists('parking_lots');
    }
};
```

- [ ] **Step 4: Run to verify pass**

Run: `docker compose exec app php artisan test --filter=ParkingSchemaTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_06_110000_create_parking_tables.php tests/Feature/Schema/ParkingSchemaTest.php
git commit -m "feat(db): add parking tables"
```

---

## Task 5: Lodging tables

**Files:**
- Create: `database/migrations/2026_06_06_120000_create_lodging_tables.php`
- Test: `tests/Feature/Schema/LodgingSchemaTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Schema/LodgingSchemaTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LodgingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_lodging_tables_exist(): void
    {
        foreach (['lodging_properties', 'rooms', 'lodging_reservations', 'lodging_sync_links'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing $table");
        }
        $this->assertTrue(Schema::hasColumns('lodging_reservations', [
            'guest_name', 'phone', 'email', 'status', 'check_in', 'check_out',
            'nights', 'price', 'direct_price', 'currency', 'source',
        ]));
    }

    public function test_reservation_status_check_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('lodging_reservations')->insert(['status' => 'teleported']);
    }

    public function test_sync_link_channel_check_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('lodging_sync_links')->insert(['channel' => 'expedia', 'ical_url' => 'http://x']);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `docker compose exec app php artisan test --filter=LodgingSchemaTest`
Expected: FAIL — `lodging_properties` missing.

- [ ] **Step 3: Create the lodging migration**

Create `database/migrations/2026_06_06_120000_create_lodging_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lodging_properties', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('name');
            $table->string('slug', 190)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'lodging_properties_source_external_unique');
        });

        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->foreignId('property_id')->constrained('lodging_properties')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'rooms_source_external_unique');
            $table->index(['property_id', 'name'], 'rooms_property_name_index');
        });

        Schema::create('lodging_reservations', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('normalized_phone', 64)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('status', 32)->nullable()->index();
            $table->date('check_in')->nullable()->index();
            $table->date('check_out')->nullable()->index();
            $table->integer('nights')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('direct_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->timestampTz('source_created_at')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'lodging_reservations_source_external_unique');
            $table->index(['room_id', 'status'], 'lodging_reservations_room_status_index');
            $table->index(['room_id', 'check_in'], 'lodging_reservations_room_checkin_index');
            $table->index(['room_id', 'check_out'], 'lodging_reservations_room_checkout_index');
        });
        DB::statement("ALTER TABLE lodging_reservations ADD CONSTRAINT lodging_reservations_status_check CHECK (status IS NULL OR status IN ('pending','confirmed','checked_in','checked_out','cancelled'))");
        DB::statement("ALTER TABLE lodging_reservations ADD CONSTRAINT lodging_reservations_source_check CHECK (source IN ('manual','booking','airbnb','direct','gmail'))");

        Schema::create('lodging_sync_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained('lodging_properties')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('channel', 32);
            $table->text('ical_url');
            $table->timestampTz('last_synced_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE lodging_sync_links ADD CONSTRAINT lodging_sync_links_channel_check CHECK (channel IN ('booking','airbnb'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('lodging_sync_links');
        Schema::dropIfExists('lodging_reservations');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('lodging_properties');
    }
};
```

- [ ] **Step 4: Run to verify pass**

Run: `docker compose exec app php artisan test --filter=LodgingSchemaTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_06_120000_create_lodging_tables.php tests/Feature/Schema/LodgingSchemaTest.php
git commit -m "feat(db): add lodging tables"
```

---

## Task 6: Rent-a-car tables

**Files:**
- Create: `database/migrations/2026_06_06_130000_create_rent_tables.php`
- Test: `tests/Feature/Schema/RentSchemaTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Schema/RentSchemaTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_rent_tables_exist(): void
    {
        foreach (['rent_vehicles', 'rent_vehicle_images', 'rent_clients',
                  'rent_contracts', 'rent_maintenance_records'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing $table");
        }
        $this->assertTrue(Schema::hasColumns('rent_vehicles', [
            'license_plate', 'chassis_vin', 'brand', 'model_name', 'manufacture_year',
            'tire_type', 'insurance_start_date', 'insurance_end_date', 'insurance_12_months',
            'itp_date', 'itp_expiry_date', 'current_km', 'monthly_rent_price',
            'daily_rent_price', 'warranty_standard', 'status',
        ]));
        $this->assertTrue(Schema::hasColumns('rent_contracts', [
            'contract_code', 'usage_type', 'start_date', 'end_date', 'km_at_handover',
            'km_at_return', 'daily_price', 'monthly_price', 'warranty_collected',
            'total_price', 'status',
        ]));
    }

    public function test_vehicle_status_check_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('rent_vehicles')->insert(['status' => 'crashed']);
    }

    public function test_contract_usage_type_check_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('rent_contracts')->insert(['usage_type' => 'taxi', 'status' => 'active']);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `docker compose exec app php artisan test --filter=RentSchemaTest`
Expected: FAIL — `rent_vehicles` missing.

- [ ] **Step 3: Create the rent migration**

Create `database/migrations/2026_06_06_130000_create_rent_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_vehicles', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('license_plate', 64)->nullable()->index();
            $table->string('chassis_vin', 190)->nullable();
            $table->string('brand', 128)->nullable();
            $table->string('model_name', 128)->nullable();
            $table->unsignedSmallInteger('manufacture_year')->nullable();
            $table->string('tire_type', 128)->nullable();
            $table->date('insurance_start_date')->nullable();
            $table->date('insurance_end_date')->nullable();
            $table->boolean('insurance_12_months')->default(false);
            $table->date('itp_date')->nullable();
            $table->date('itp_expiry_date')->nullable();
            $table->integer('current_km')->nullable();
            $table->decimal('monthly_rent_price', 12, 2)->nullable();
            $table->decimal('daily_rent_price', 12, 2)->nullable();
            $table->decimal('warranty_standard', 12, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->string('status', 32)->default('available')->index();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'rent_vehicles_source_external_unique');
            $table->index('itp_expiry_date', 'rent_vehicles_itp_expiry_index');
            $table->index('insurance_end_date', 'rent_vehicles_insurance_end_index');
        });
        DB::statement("ALTER TABLE rent_vehicles ADD CONSTRAINT rent_vehicles_status_check CHECK (status IN ('available','rented','service'))");

        Schema::create('rent_vehicle_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rent_vehicle_id')->constrained('rent_vehicles')->cascadeOnDelete();
            $table->text('path');
            $table->string('caption')->nullable();
            $table->timestampsTz();
        });

        Schema::create('rent_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('name')->nullable()->index();
            $table->string('phone', 64)->nullable();
            $table->string('normalized_phone', 64)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('identity_document', 190)->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'rent_clients_source_external_unique');
        });

        Schema::create('rent_contracts', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('contract_code', 190)->nullable();
            $table->foreignId('rent_vehicle_id')->nullable()->constrained('rent_vehicles')->nullOnDelete();
            $table->foreignId('rent_client_id')->nullable()->constrained('rent_clients')->nullOnDelete();
            $table->string('usage_type', 32);
            $table->date('start_date')->nullable()->index();
            $table->date('end_date')->nullable()->index();
            $table->integer('km_at_handover')->nullable();
            $table->integer('km_at_return')->nullable();
            $table->decimal('daily_price', 12, 2)->nullable();
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->decimal('warranty_collected', 12, 2)->nullable();
            $table->decimal('total_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->string('status', 32)->default('active');
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'rent_contracts_source_external_unique');
            $table->index(['rent_vehicle_id', 'status'], 'rent_contracts_vehicle_status_index');
            $table->index(['status', 'end_date'], 'rent_contracts_status_end_index');
        });
        DB::statement("ALTER TABLE rent_contracts ADD CONSTRAINT rent_contracts_usage_type_check CHECK (usage_type IN ('rent','uber','bolt'))");
        DB::statement("ALTER TABLE rent_contracts ADD CONSTRAINT rent_contracts_status_check CHECK (status IN ('active','completed','cancelled'))");

        Schema::create('rent_maintenance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rent_vehicle_id')->constrained('rent_vehicles')->cascadeOnDelete();
            $table->timestampTz('service_at')->nullable()->index();
            $table->integer('mileage_at_service')->nullable();
            $table->string('intervention_type', 190)->nullable();
            $table->integer('next_service_km')->nullable();
            $table->text('details')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_maintenance_records');
        Schema::dropIfExists('rent_contracts');
        Schema::dropIfExists('rent_clients');
        Schema::dropIfExists('rent_vehicle_images');
        Schema::dropIfExists('rent_vehicles');
    }
};
```

- [ ] **Step 4: Run to verify pass**

Run: `docker compose exec app php artisan test --filter=RentSchemaTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_06_130000_create_rent_tables.php tests/Feature/Schema/RentSchemaTest.php
git commit -m "feat(db): add rent-a-car tables"
```

---

## Task 7: Payments tables

**Files:**
- Create: `database/migrations/2026_06_06_140000_create_payment_tables.php`
- Test: `tests/Feature/Schema/PaymentSchemaTest.php`

Note: this migration's timestamp is later than parking/lodging/rent so its foreign keys resolve.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Schema/PaymentSchemaTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_tables_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('payments', [
            'service', 'parking_reservation_id', 'lodging_reservation_id',
            'rent_contract_id', 'amount', 'currency', 'method', 'paid_at',
        ]));
        $this->assertTrue(Schema::hasTable('payment_change_audits'));
    }

    public function test_payment_service_check_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('payments')->insert([
            'service' => 'gym', 'amount' => 10, 'method' => 'cash',
        ]);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `docker compose exec app php artisan test --filter=PaymentSchemaTest`
Expected: FAIL — `payments` missing.

- [ ] **Step 3: Create the payment migration**

Create `database/migrations/2026_06_06_140000_create_payment_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('service', 32);
            $table->foreignId('parking_reservation_id')->nullable()->constrained('parking_reservations')->nullOnDelete();
            $table->foreignId('lodging_reservation_id')->nullable()->constrained('lodging_reservations')->nullOnDelete();
            $table->foreignId('rent_contract_id')->nullable()->constrained('rent_contracts')->nullOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('RON');
            $table->string('method', 32);
            $table->timestampTz('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['service', 'paid_at'], 'payments_service_paid_index');
            $table->index('method', 'payments_method_index');
            $table->index(['source', 'external_id'], 'payments_source_external_index');
        });
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_service_check CHECK (service IN ('parking','lodging','rent'))");

        Schema::create('payment_change_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->jsonb('changed_fields')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_change_audits');
        Schema::dropIfExists('payments');
    }
};
```

- [ ] **Step 4: Run to verify pass**

Run: `docker compose exec app php artisan test --filter=PaymentSchemaTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_06_140000_create_payment_tables.php tests/Feature/Schema/PaymentSchemaTest.php
git commit -m "feat(db): add payment tables"
```

---

## Task 8: Budget tables

**Files:**
- Create: `database/migrations/2026_06_06_150000_create_budget_tables.php`
- Test: `tests/Feature/Schema/BudgetSchemaTest.php`

Note: `budget_transactions.raw_message_id` and `budget_raw_messages.transaction_id` form a
nullable circular FK, so `budget_raw_messages` is created first and the FK from messages to
transactions is added with a later `ALTER TABLE`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Schema/BudgetSchemaTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BudgetSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_tables_exist(): void
    {
        foreach (['budget_categories', 'budget_raw_messages', 'budget_transactions', 'salaries'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing $table");
        }
        $this->assertTrue(Schema::hasColumns('budget_categories', ['service', 'name', 'kind', 'frequency']));
        $this->assertTrue(Schema::hasColumns('budget_transactions', ['type', 'amount', 'currency', 'occurred_on']));
    }

    public function test_category_frequency_check_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('budget_categories')->insert([
            'service' => 'hotel', 'name' => 'apă', 'kind' => 'expense', 'frequency' => 'hourly',
        ]);
    }

    public function test_transaction_type_check_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('budget_transactions')->insert([
            'type' => 'refund', 'amount' => 10, 'occurred_on' => now()->toDateString(),
        ]);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `docker compose exec app php artisan test --filter=BudgetSchemaTest`
Expected: FAIL — `budget_categories` missing.

- [ ] **Step 3: Create the budget migration**

Create `database/migrations/2026_06_06_150000_create_budget_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('service', 32);
            $table->string('name', 190);
            $table->string('kind', 16);
            $table->string('frequency', 16);
            $table->decimal('default_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE budget_categories ADD CONSTRAINT budget_categories_service_check CHECK (service IN ('hotel','parcare','rent','general'))");
        DB::statement("ALTER TABLE budget_categories ADD CONSTRAINT budget_categories_kind_check CHECK (kind IN ('expense','income'))");
        DB::statement("ALTER TABLE budget_categories ADD CONSTRAINT budget_categories_frequency_check CHECK (frequency IN ('daily','weekly','monthly','quarterly','yearly','once'))");

        Schema::create('budget_raw_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('chat_id', 64);
            $table->string('message_id', 64);
            $table->text('text');
            $table->boolean('parsed')->default(false);
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->timestampTz('received_at')->useCurrent();
            $table->timestampsTz();
            $table->unique(['chat_id', 'message_id'], 'budget_raw_messages_chat_message_unique');
        });

        Schema::create('budget_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('type', 16);
            $table->foreignId('category_id')->nullable()->constrained('budget_categories')->nullOnDelete();
            $table->string('service', 32)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('RON');
            $table->date('occurred_on');
            $table->text('description')->nullable();
            $table->string('telegram_chat', 32)->nullable();
            $table->foreignId('raw_message_id')->nullable()->constrained('budget_raw_messages')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'budget_transactions_source_external_unique');
            $table->index(['type', 'occurred_on'], 'budget_transactions_type_occurred_index');
            $table->index(['service', 'occurred_on'], 'budget_transactions_service_occurred_index');
        });
        DB::statement("ALTER TABLE budget_transactions ADD CONSTRAINT budget_transactions_type_check CHECK (type IN ('income','expense'))");

        // Close the circular link now that budget_transactions exists.
        Schema::table('budget_raw_messages', function (Blueprint $table): void {
            $table->foreign('transaction_id')->references('id')->on('budget_transactions')->nullOnDelete();
        });

        Schema::create('salaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_name')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('RON');
            $table->date('period_month');
            $table->timestampTz('paid_at')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE salaries ADD CONSTRAINT salaries_status_check CHECK (status IN ('pending','paid'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('salaries');
        Schema::table('budget_raw_messages', function (Blueprint $table): void {
            $table->dropForeign(['transaction_id']);
        });
        Schema::dropIfExists('budget_transactions');
        Schema::dropIfExists('budget_raw_messages');
        Schema::dropIfExists('budget_categories');
    }
};
```

- [ ] **Step 4: Run to verify pass**

Run: `docker compose exec app php artisan test --filter=BudgetSchemaTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_06_150000_create_budget_tables.php tests/Feature/Schema/BudgetSchemaTest.php
git commit -m "feat(db): add budget tables"
```

---

## Task 9: Reference seeders

**Files:**
- Create: `database/seeders/ParkingReferenceSeeder.php`
- Create: `database/seeders/LodgingReferenceSeeder.php`
- Create: `database/seeders/MessageTemplateSeeder.php`
- Create: `database/seeders/BudgetCategorySeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Schema/SeederTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Schema/SeederTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_parking_lots_and_zones(): void
    {
        $this->seed(\Database\Seeders\ParkingReferenceSeeder::class);

        $this->assertDatabaseHas('parking_lots', ['name' => 'Parcarea 1', 'total_spaces' => 54]);
        $this->assertDatabaseHas('parking_lots', ['name' => 'Parcarea 2', 'total_spaces' => 30]);
        // zones A-E for Parcarea 1
        $this->assertSame(5, DB::table('parking_zones')->count());
        $this->assertSame(54, (int) DB::table('parking_zones')->sum('capacity'));
    }

    public function test_seeds_two_properties_with_rooms(): void
    {
        $this->seed(\Database\Seeders\LodgingReferenceSeeder::class);

        $this->assertDatabaseHas('lodging_properties', ['name' => 'Sky Center']);
        $this->assertDatabaseHas('lodging_properties', ['name' => 'Serafim']);
        $this->assertSame(12, DB::table('rooms')->count()); // 7 + 5
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `docker compose exec app php artisan test --filter=SeederTest`
Expected: FAIL — class `ParkingReferenceSeeder` not found.

- [ ] **Step 3: Create ParkingReferenceSeeder**

Create `database/seeders/ParkingReferenceSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParkingReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $lot1 = DB::table('parking_lots')->insertGetId([
            'name' => 'Parcarea 1', 'total_spaces' => 54,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('parking_lots')->insert([
            'name' => 'Parcarea 2', 'total_spaces' => 30,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $zones = ['A' => 11, 'B' => 8, 'C' => 14, 'D' => 12, 'E' => 9];
        foreach ($zones as $code => $capacity) {
            DB::table('parking_zones')->insert([
                'lot_id' => $lot1, 'code' => $code, 'capacity' => $capacity,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
```

- [ ] **Step 4: Create LodgingReferenceSeeder**

Create `database/seeders/LodgingReferenceSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LodgingReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $properties = ['Sky Center' => 7, 'Serafim' => 5];
        foreach ($properties as $name => $roomCount) {
            $propertyId = DB::table('lodging_properties')->insertGetId([
                'name' => $name, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            for ($i = 1; $i <= $roomCount; $i++) {
                DB::table('rooms')->insert([
                    'property_id' => $propertyId, 'name' => "Camera $i", 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }
}
```

- [ ] **Step 5: Create MessageTemplateSeeder**

Create `database/seeders/MessageTemplateSeeder.php`:

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
            ['template_key' => 'parking_booked_confirm', 'service' => 'parking', 'channel' => 'whatsapp',
             'label' => 'Confirmare parcare', 'body' => 'Bună ziua! Rezervarea dvs. de parcare a fost confirmată.'],
            ['template_key' => 'parking_departed_review', 'service' => 'parking', 'channel' => 'whatsapp',
             'label' => 'Follow-up recenzie', 'body' => 'Vă mulțumim! Cum ați aflat de noi? Ne lăsați o recenzie pe Google/Facebook?'],
        ];
        foreach ($templates as $t) {
            DB::table('message_templates')->insert(array_merge($t, [
                'source' => 'manual', 'locale' => 'ro', 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    }
}
```

- [ ] **Step 6: Create BudgetCategorySeeder**

Create `database/seeders/BudgetCategorySeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BudgetCategorySeeder extends Seeder
{
    public function run(): void
    {
        $hotel = ['apă', 'lumină', 'gaz', 'electricitate', 'inventar'];
        foreach ($hotel as $name) {
            DB::table('budget_categories')->insert([
                'service' => 'hotel', 'name' => $name, 'kind' => 'expense',
                'frequency' => 'monthly', 'currency' => 'RON', 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
```

- [ ] **Step 7: Wire seeders into DatabaseSeeder**

Replace the body of `run()` in `database/seeders/DatabaseSeeder.php` with:

```php
public function run(): void
{
    $this->call([
        ParkingReferenceSeeder::class,
        LodgingReferenceSeeder::class,
        MessageTemplateSeeder::class,
        BudgetCategorySeeder::class,
    ]);
}
```

- [ ] **Step 8: Run to verify pass**

Run: `docker compose exec app php artisan test --filter=SeederTest`
Expected: PASS (2 tests).

- [ ] **Step 9: Commit**

```bash
git add database/seeders/ tests/Feature/Schema/SeederTest.php
git commit -m "feat(db): add reference seeders"
```

---

## Task 10: Full-stack verification

**Files:** none (verification only)

- [ ] **Step 1: Rebuild the database from scratch with seeds**

Run:
```bash
docker compose exec app php artisan migrate:fresh --seed --force
```
Expected: all migrations run in order, all seeders run, output ends with `DONE`. No FK or CHECK errors.

- [ ] **Step 2: Run the full test suite**

Run:
```bash
docker compose exec app php artisan test
```
Expected: ALL tests pass (Common 5, Parking 3, Lodging 3, Rent 3, Payment 2, Budget 3, Seeder 2, plus Laravel defaults).

- [ ] **Step 3: Spot-check the schema in psql**

Run:
```bash
docker compose exec pgsql psql -U skycenter -d skycenter_app -c "\dt"
```
Expected: all ~24 tables listed (parking_*, lodging_*, rooms, rent_*, payments, payment_change_audits, budget_*, salaries, message_templates, outbound_messages, automation_*, users, plus Laravel system tables).

- [ ] **Step 4: Commit any final tweaks**

```bash
git add -A
git commit -m "test: verify full schema migrate:fresh --seed" --allow-empty
```

---

## Self-Review notes (already applied)

- **Spec coverage:** every table in the spec maps to a task — common (T2–T3, T7), parking (T4), lodging (T5), rent (T6), budget (T8), seeders for fixed data (T9). The `departed+24h` flow is supported by `parking_status_audits` + `outbound_messages` (T3/T4); Telegram budget parsing by `budget_raw_messages` (T8); lodging iCal by `lodging_sync_links` (T5).
- **Naming consistency:** payments FK is `rent_contract_id` → table `rent_contracts` (consistent). `parking_space_id` references `parking_spaces`. Budget circular FK handled explicitly.
- **Out of scope (per spec):** Eloquent models, web UI/Filament, n8n flows, orar, marketing, Android.
- **Deferred data:** `parking_prices`, `parking_spaces` positions, lodging sync URLs and the exact price table are populated later by the user, not seeded here.
