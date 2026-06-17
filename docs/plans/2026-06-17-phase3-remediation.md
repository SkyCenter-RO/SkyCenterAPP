# Phase 3 Audit Remediation Implementation Plan

> **For Antigravity:** REQUIRED WORKFLOW: Use `.agent/workflows/execute-plan.md` to execute this plan in single-flow mode.

**Goal:** Implement Phase 3 of the audit remediation plan targeting low-priority findings (select dropdowns, Pint formatting, file uploader size limits, and placeholder sanitization).

**Architecture:** Create backed PHP string enums for all database check-constrained status fields. Update models to cast status to enums and update Filament forms to use Select fields referencing them. Add maxSize to file uploader and regex replacement to strip unresolved template placeholders. Run Pint to format files.

**Tech Stack:** PHP 8.2, Laravel 11, Filament v3, PHPUnit

---

### Task 1: Create Enums and Cast Models

**Files:**
- Create: `app/Enums/ParkingReservationStatus.php`
- Create: `app/Enums/LodgingReservationStatus.php`
- Create: `app/Enums/RentContractStatus.php`
- Create: `app/Enums/RentVehicleStatus.php`
- Create: `app/Enums/SalaryStatus.php`
- Create: `app/Enums/OutboundMessageStatus.php`
- Create: `app/Enums/AutomationWebhookLogStatus.php`
- Modify: `app/Models/ParkingReservation.php`
- Modify: `app/Models/LodgingReservation.php`
- Modify: `app/Models/RentContract.php`
- Modify: `app/Models/RentVehicle.php`
- Modify: `app/Models/Salary.php`
- Modify: `app/Models/OutboundMessage.php`
- Modify: `app/Models/AutomationWebhookLog.php`
- Create: `tests/Feature/Schema/EnumCastsTest.php`

**Step 1: Write the failing test**
Create `tests/Feature/Schema/EnumCastsTest.php`:
```php
<?php

namespace Tests\Feature\Schema;

use App\Models\ParkingReservation;
use App\Models\LodgingReservation;
use App\Models\RentContract;
use App\Models\RentVehicle;
use App\Models\Salary;
use App\Models\OutboundMessage;
use App\Models\AutomationWebhookLog;
use App\Enums\ParkingReservationStatus;
use App\Enums\LodgingReservationStatus;
use App\Enums\RentContractStatus;
use App\Enums\RentVehicleStatus;
use App\Enums\SalaryStatus;
use App\Enums\OutboundMessageStatus;
use App\Enums\AutomationWebhookLogStatus;
use Tests\TestCase;

class EnumCastsTest extends TestCase
{
    public function test_models_cast_status_to_enums(): void
    {
        $this->assertSame(ParkingReservationStatus::class, (new ParkingReservation)->getCasts()['status'] ?? null);
        $this->assertSame(LodgingReservationStatus::class, (new LodgingReservation)->getCasts()['status'] ?? null);
        $this->assertSame(RentContractStatus::class, (new RentContract)->getCasts()['status'] ?? null);
        $this->assertSame(RentVehicleStatus::class, (new RentVehicle)->getCasts()['status'] ?? null);
        $this->assertSame(SalaryStatus::class, (new Salary)->getCasts()['status'] ?? null);
        $this->assertSame(OutboundMessageStatus::class, (new OutboundMessage)->getCasts()['status'] ?? null);
        $this->assertSame(AutomationWebhookLogStatus::class, (new AutomationWebhookLog)->getCasts()['status'] ?? null);
    }
}
```

**Step 2: Run test to verify it fails**
Run: `docker compose exec app php artisan test --filter=EnumCastsTest`
Expected: Fails because enums do not exist and casts are not configured.

**Step 3: Write minimal implementation**
Create the 7 enum files under `app/Enums/`:
`app/Enums/ParkingReservationStatus.php`:
```php
<?php

namespace App\Enums;

enum ParkingReservationStatus: string
{
    case PENDING_APPROVAL = 'pending_approval';
    case BOOKED = 'booked';
    case PARKED = 'parked';
    case DEPARTED = 'departed';
    case CANCELLED = 'cancelled';
}
```
`app/Enums/LodgingReservationStatus.php`:
```php
<?php

namespace App\Enums;

enum LodgingReservationStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CHECKED_IN = 'checked_in';
    case CHECKED_OUT = 'checked_out';
    case CANCELLED = 'cancelled';
}
```
`app/Enums/RentContractStatus.php`:
```php
<?php

namespace App\Enums;

enum RentContractStatus: string
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
```
`app/Enums/RentVehicleStatus.php`:
```php
<?php

namespace App\Enums;

enum RentVehicleStatus: string
{
    case AVAILABLE = 'available';
    case RENTED = 'rented';
    case SERVICE = 'service';
}
```
`app/Enums/SalaryStatus.php`:
```php
<?php

namespace App\Enums;

enum SalaryStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
}
```
`app/Enums/OutboundMessageStatus.php`:
```php
<?php

namespace App\Enums;

enum OutboundMessageStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
```
`app/Enums/AutomationWebhookLogStatus.php`:
```php
<?php

namespace App\Enums;

enum AutomationWebhookLogStatus: string
{
    case RECEIVED = 'received';
    case PROCESSED = 'processed';
    case ERROR = 'error';
}
```

Add casts to the 7 model files in `app/Models/`:
Update `app/Models/ParkingReservation.php`, adding `status` to `$casts` array:
```php
        'status' => \App\Enums\ParkingReservationStatus::class,
```
And similarly for the other 6 models:
- `app/Models/LodgingReservation.php`: `'status' => \App\Enums\LodgingReservationStatus::class`
- `app/Models/RentContract.php`: `'status' => \App\Enums\RentContractStatus::class`
- `app/Models/RentVehicle.php`: `'status' => \App\Enums\RentVehicleStatus::class`
- `app/Models/Salary.php`: `'status' => \App\Enums\SalaryStatus::class`
- `app/Models/OutboundMessage.php`: `'status' => \App\Enums\OutboundMessageStatus::class`
- `app/Models/AutomationWebhookLog.php`: `'status' => \App\Enums\AutomationWebhookLogStatus::class`

**Step 4: Run test to verify it passes**
Run: `docker compose exec app php artisan test --filter=EnumCastsTest`
Expected: PASS

**Step 5: Commit**
```bash
git add app/Enums/*.php app/Models/*.php tests/Feature/Schema/EnumCastsTest.php
git commit -m "feat: add status enums and configure model casts"
```

---

### Task 2: Update Filament Form Schemas to use Select Dropdowns

**Files:**
- Modify: `app/Filament/Resources/ParkingReservations/Schemas/ParkingReservationForm.php`
- Modify: `app/Filament/Resources/LodgingReservations/Schemas/LodgingReservationForm.php`
- Modify: `app/Filament/Resources/RentContracts/Schemas/RentContractForm.php`
- Modify: `app/Filament/Resources/RentVehicles/Schemas/RentVehicleForm.php`
- Modify: `app/Filament/Resources/Salaries/Schemas/SalaryForm.php`
- Modify: `app/Filament/Resources/OutboundMessages/Schemas/OutboundMessageForm.php`
- Modify: `app/Filament/Resources/AutomationWebhookLogs/Schemas/AutomationWebhookLogForm.php`
- Create: `tests/Feature/Panel/FilamentFormsStatusSelectTest.php`

**Step 1: Write the failing test**
Create `tests/Feature/Panel/FilamentFormsStatusSelectTest.php`:
```php
<?php

namespace Tests\Feature\Panel;

use App\Filament\Resources\ParkingReservations\Schemas\ParkingReservationForm;
use App\Filament\Resources\LodgingReservations\Schemas\LodgingReservationForm;
use App\Filament\Resources\RentContracts\Schemas\RentContractForm;
use App\Filament\Resources\RentVehicles\Schemas\RentVehicleForm;
use App\Filament\Resources\Salaries\Schemas\SalaryForm;
use App\Filament\Resources\OutboundMessages\Schemas\OutboundMessageForm;
use App\Filament\Resources\AutomationWebhookLogs\Schemas\AutomationWebhookLogForm;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Tests\TestCase;

class FilamentFormsStatusSelectTest extends TestCase
{
    private function getField(string $formClass, string $fieldName)
    {
        $schema = new Schema();
        $formClass::configure($schema);
        foreach ($schema->getComponents() as $component) {
            if ($component->getName() === $fieldName) {
                return $component;
            }
        }
        return null;
    }

    public function test_status_fields_are_selects_with_enum_options(): void
    {
        $fields = [
            ParkingReservationForm::class,
            LodgingReservationForm::class,
            RentContractForm::class,
            RentVehicleForm::class,
            SalaryForm::class,
            OutboundMessageForm::class,
            AutomationWebhookLogForm::class,
        ];

        foreach ($fields as $formClass) {
            $field = $this->getField($formClass, 'status');
            $this->assertNotNull($field, "Status field missing on {$formClass}");
            $this->assertInstanceOf(Select::class, $field, "Status field on {$formClass} is not a Select");
        }
    }
}
```

**Step 2: Run test to verify it fails**
Run: `docker compose exec app php artisan test --filter=FilamentFormsStatusSelectTest`
Expected: Fails because status fields are still TextInputs.

**Step 3: Write minimal implementation**
Modify each form schema class, replacing `TextInput::make('status')` with `Select::make('status')` and configuring it with the appropriate enum class using `->options(EnumClass::class)`. Import the `Select` component if not already imported.

In `app/Filament/Resources/ParkingReservations/Schemas/ParkingReservationForm.php`:
```php
                Select::make('status')
                    ->options(\App\Enums\ParkingReservationStatus::class)
                    ->required()
                    ->default(\App\Enums\ParkingReservationStatus::PENDING_APPROVAL),
```

In `app/Filament/Resources/LodgingReservations/Schemas/LodgingReservationForm.php`:
```php
                Select::make('status')
                    ->options(\App\Enums\LodgingReservationStatus::class)
                    ->nullable(),
```

In `app/Filament/Resources/RentContracts/Schemas/RentContractForm.php`:
```php
                Select::make('status')
                    ->options(\App\Enums\RentContractStatus::class)
                    ->required()
                    ->default(\App\Enums\RentContractStatus::ACTIVE),
```

In `app/Filament/Resources/RentVehicles/Schemas/RentVehicleForm.php`:
```php
                Select::make('status')
                    ->options(\App\Enums\RentVehicleStatus::class)
                    ->required()
                    ->default(\App\Enums\RentVehicleStatus::AVAILABLE),
```

In `app/Filament/Resources/Salaries/Schemas/SalaryForm.php`:
```php
                Select::make('status')
                    ->options(\App\Enums\SalaryStatus::class)
                    ->required()
                    ->default(\App\Enums\SalaryStatus::PENDING),
```

In `app/Filament/Resources/OutboundMessages/Schemas/OutboundMessageForm.php`:
```php
                Select::make('status')
                    ->options(\App\Enums\OutboundMessageStatus::class)
                    ->required()
                    ->default(\App\Enums\OutboundMessageStatus::PENDING),
```

In `app/Filament/Resources/AutomationWebhookLogs/Schemas/AutomationWebhookLogForm.php`:
```php
                Select::make('status')
                    ->options(\App\Enums\AutomationWebhookLogStatus::class)
                    ->required(),
```

**Step 4: Run test to verify it passes**
Run: `docker compose exec app php artisan test --filter=FilamentFormsStatusSelectTest`
Expected: PASS

**Step 5: Commit**
```bash
git add app/Filament/Resources/**/*.php tests/Feature/Panel/FilamentFormsStatusSelectTest.php
git commit -m "feat: convert Filament status fields from TextInput to Select"
```

---

### Task 3: Configure Size Limit on Schedule PDF Uploader

**Files:**
- Modify: `app/Filament/Pages/OrdineaDeZi.php`
- Modify: `tests/Feature/Scheduling/OrdineaDeZiUploadTest.php`

**Step 1: Write the failing test**
Modify `tests/Feature/Scheduling/OrdineaDeZiUploadTest.php` to add a test asserting that the file uploader has the `maxSize` rule set:
```php
    public function test_uploader_contains_max_size_constraint(): void
    {
        $page = new \App\Filament\Pages\OrdineaDeZi();
        // Retrieve page actions
        $refMethod = new \ReflectionMethod($page, 'getHeaderActions');
        $refMethod->setAccessible(true);
        $actions = $refMethod->invoke($page);

        $uploadAction = null;
        foreach ($actions as $action) {
            if ($action->getName() === 'uploadSchedule') {
                $uploadAction = $action;
                break;
            }
        }

        $this->assertNotNull($uploadAction);
        $formComponents = $uploadAction->getForm();
        $pdfField = null;
        foreach ($formComponents as $component) {
            if ($component->getName() === 'schedule_pdf') {
                $pdfField = $component;
                break;
            }
        }

        $this->assertNotNull($pdfField);
        $this->assertSame(5120, $pdfField->getMaxSize());
    }
```

**Step 2: Run test to verify it fails**
Run: `docker compose exec app php artisan test --filter=OrdineaDeZiUploadTest`
Expected: Fails on the new test asserting `maxSize` is 5120.

**Step 3: Write minimal implementation**
In `app/Filament/Pages/OrdineaDeZi.php`, chain `->maxSize(5120)` to `FileUpload::make('schedule_pdf')` inside `getHeaderActions()`:
```php
                    FileUpload::make('schedule_pdf')
                        ->label('Fișier PDF Program Ture')
                        ->acceptedFileTypes(['application/pdf'])
                        ->required()
                        ->maxSize(5120)
                        ->disk('local')
                        ->directory('temp-schedules'),
```

**Step 4: Run test to verify it passes**
Run: `docker compose exec app php artisan test --filter=OrdineaDeZiUploadTest`
Expected: PASS

**Step 5: Commit**
```bash
git add app/Filament/Pages/OrdineaDeZi.php tests/Feature/Scheduling/OrdineaDeZiUploadTest.php
git commit -m "feat: add 5MB size limit to schedule PDF uploader"
```

---

### Task 4: Sanitize Outbound Message Template Placeholders

**Files:**
- Modify: `app/Actions/Messaging/RenderMessageTemplate.php`
- Modify: `tests/Feature/Actions/Messaging/RenderMessageTemplateTest.php`

**Step 1: Write the failing test**
In `tests/Feature/Actions/Messaging/RenderMessageTemplateTest.php`, add `test_strips_unresolved_placeholders()`:
```php
    public function test_strips_unresolved_placeholders(): void
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

        $action = new RenderMessageTemplate;

        $result = $action->handle('parking', 'confirmation', [
            'name' => 'Ion',
        ]);

        $this->assertSame([
            'channel' => 'whatsapp',
            'text' => 'Buna Ion, auto .',
        ], $result);
    }
```

**Step 2: Run test to verify it fails**
Run: `docker compose exec app php artisan test --filter=RenderMessageTemplateTest`
Expected: Fails because unresolved `{{plate}}` remains in the rendered string: `"Buna Ion, auto {{plate}}."`

**Step 3: Write minimal implementation**
In `app/Actions/Messaging/RenderMessageTemplate.php`, use `preg_replace` to remove all unresolved placeholders (e.g. `{{placeholder}}`):
```php
        $text = strtr($template->body, $this->wrapPlaceholders($placeholders));
        $text = preg_replace('/\{\{[^}]*\}\}/', '', $text);

        return ['channel' => $template->channel, 'text' => $text];
```

**Step 4: Run test to verify it passes**
Run: `docker compose exec app php artisan test --filter=RenderMessageTemplateTest`
Expected: PASS

**Step 5: Commit**
```bash
git add app/Actions/Messaging/RenderMessageTemplate.php tests/Feature/Actions/Messaging/RenderMessageTemplateTest.php
git commit -m "feat: strip unresolved placeholders from rendered templates"
```

---

### Task 5: Run Pint Formatter and Verify Whole Suite

**Files:**
- Modify: All formatted files

**Step 1: Run Pint Formatter**
Run: `docker compose exec app vendor/bin/pint`
Expected: Mechanical formatting changes applied automatically to multiple project files.

**Step 2: Run Pint check to verify exit status is 0**
Run: `docker compose exec app vendor/bin/pint --test`
Expected: Exit code 0, all files match Pint styling rules.

**Step 3: Run full automated test suite**
Run: `docker compose exec app php artisan test`
Expected: PASS (191+ tests pass, including the new tests created).

**Step 4: Commit**
```bash
git add .
git commit -m "style: format codebase with Pint"
```
