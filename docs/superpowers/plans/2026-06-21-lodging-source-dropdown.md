# Lodging Source Dropdown Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the free-text lodging reservation source with an approved dropdown and remove the irrelevant source field from property and room forms.

**Architecture:** Keep the database schema and webhook contract unchanged. The reservation form owns the four operator-facing options and dynamically appends only the current record's legacy value during editing; property and room forms rely on the existing database default of `manual` after their source components are removed.

**Tech Stack:** PHP 8.2, Laravel 12, Filament 5.6, PHPUnit 11

---

## File Structure

- Create `tests/Feature/Panel/LodgingSourceSelectTest.php`: focused component tests for reservation options, legacy compatibility, and removed property/room fields.
- Modify `app/Filament/Resources/LodgingReservations/Schemas/LodgingReservationForm.php`: replace the source `TextInput` with a required `Select`, define current options, and append an existing legacy value on edit.
- Modify `app/Filament/Resources/LodgingProperties/Schemas/LodgingPropertyForm.php`: remove the source component.
- Modify `app/Filament/Resources/Rooms/Schemas/RoomForm.php`: remove the source component.
- Modify `tests/Feature/Panel/LodgingModelsTest.php`: assert that the database still defaults new properties and rooms to `manual`.

### Task 1: Reservation Source Dropdown

**Files:**
- Create: `tests/Feature/Panel/LodgingSourceSelectTest.php`
- Modify: `app/Filament/Resources/LodgingReservations/Schemas/LodgingReservationForm.php`

- [ ] **Step 1: Write failing tests for current reservation sources and legacy edit compatibility**

Create `tests/Feature/Panel/LodgingSourceSelectTest.php` with:

```php
<?php

namespace Tests\Feature\Panel;

use App\Filament\Resources\LodgingProperties\Schemas\LodgingPropertyForm;
use App\Filament\Resources\LodgingReservations\Schemas\LodgingReservationForm;
use App\Filament\Resources\Rooms\Schemas\RoomForm;
use App\Models\LodgingReservation;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LodgingSourceSelectTest extends TestCase
{
    private function getField(
        string $formClass,
        string $fieldName,
        ?Model $record = null,
    ): ?Component {
        $schema = (new Schema)->record($record);
        $formClass::configure($schema);

        foreach ($schema->getComponents() as $component) {
            if ($component->getName() === $fieldName) {
                return $component;
            }
        }

        return null;
    }

    public function test_new_reservation_source_is_a_required_select_with_approved_options(): void
    {
        $field = $this->getField(LodgingReservationForm::class, 'source');

        $this->assertInstanceOf(Select::class, $field);
        $this->assertTrue($field->isRequired());
        $this->assertSame('direct', $field->getDefaultState());
        $this->assertSame([
            'gmail' => 'Email',
            'booking_com' => 'Booking.com',
            'airbnb' => 'Airbnb',
            'direct' => 'Direct',
        ], $field->getOptions());
    }

    #[DataProvider('legacySources')]
    public function test_editing_a_legacy_reservation_preserves_its_current_source(
        string $source,
        string $label,
    ): void {
        $record = new LodgingReservation(['source' => $source]);
        $field = $this->getField(LodgingReservationForm::class, 'source', $record);

        $this->assertInstanceOf(Select::class, $field);
        $this->assertSame($label, $field->getOptions()[$source] ?? null);
    }

    public static function legacySources(): array
    {
        return [
            ['manual', 'Manual (legacy)'],
            ['booking', 'Booking (legacy)'],
        ];
    }
}
```

- [ ] **Step 2: Run the focused tests and verify RED**

Run:

```powershell
php artisan test tests/Feature/Panel/LodgingSourceSelectTest.php
```

Expected: FAIL because the existing `source` component is a `TextInput`, defaults to `manual`, and has no dropdown options.

- [ ] **Step 3: Implement the reservation source dropdown**

In `app/Filament/Resources/LodgingReservations/Schemas/LodgingReservationForm.php`, replace the existing source `TextInput` with:

```php
Select::make('source')
    ->options(fn (?LodgingReservation $record): array => self::sourceOptions($record))
    ->required()
    ->default('direct'),
```

Add these constants and helper at class level:

```php
private const SOURCE_OPTIONS = [
    'gmail' => 'Email',
    'booking_com' => 'Booking.com',
    'airbnb' => 'Airbnb',
    'direct' => 'Direct',
];

private const LEGACY_SOURCE_LABELS = [
    'manual' => 'Manual (legacy)',
    'booking' => 'Booking (legacy)',
];

private static function sourceOptions(?LodgingReservation $record): array
{
    $options = self::SOURCE_OPTIONS;

    if ($record && isset(self::LEGACY_SOURCE_LABELS[$record->source])) {
        $options[$record->source] = self::LEGACY_SOURCE_LABELS[$record->source];
    }

    return $options;
}
```

Keep the existing `TextInput` import because the form still uses it for its other fields.

- [ ] **Step 4: Run the focused tests and verify GREEN**

Run:

```powershell
php artisan test tests/Feature/Panel/LodgingSourceSelectTest.php
```

Expected: PASS, 3 tests.

- [ ] **Step 5: Commit the reservation dropdown**

```powershell
git add -- tests/Feature/Panel/LodgingSourceSelectTest.php app/Filament/Resources/LodgingReservations/Schemas/LodgingReservationForm.php
git commit -m "feat: constrain lodging reservation sources"
```

### Task 2: Remove Property and Room Source Fields

**Files:**
- Modify: `tests/Feature/Panel/LodgingSourceSelectTest.php`
- Modify: `app/Filament/Resources/LodgingProperties/Schemas/LodgingPropertyForm.php`
- Modify: `app/Filament/Resources/Rooms/Schemas/RoomForm.php`

- [ ] **Step 1: Add failing tests that property and room forms omit source**

Add these methods to `LodgingSourceSelectTest`:

```php
#[DataProvider('formsWithoutSource')]
public function test_internal_lodging_configuration_forms_do_not_expose_source(
    string $formClass,
): void {
    $this->assertNull($this->getField($formClass, 'source'));
}

public static function formsWithoutSource(): array
{
    return [
        [LodgingPropertyForm::class],
        [RoomForm::class],
    ];
}
```

- [ ] **Step 2: Run the new test and verify RED**

Run:

```powershell
php artisan test tests/Feature/Panel/LodgingSourceSelectTest.php --filter=internal_lodging_configuration
```

Expected: FAIL twice because both forms currently contain `source` fields.

- [ ] **Step 3: Remove the two source components**

Delete this component from both `LodgingPropertyForm::configure()` and `RoomForm::configure()`:

```php
TextInput::make('source')
    ->required()
    ->default('manual'),
```

Keep each file's `TextInput` import because both forms still contain other text inputs.

- [ ] **Step 4: Run the focused source tests and verify GREEN**

Run:

```powershell
php artisan test tests/Feature/Panel/LodgingSourceSelectTest.php
```

Expected: PASS, 5 tests.

- [ ] **Step 5: Commit the removed fields**

```powershell
git add -- tests/Feature/Panel/LodgingSourceSelectTest.php app/Filament/Resources/LodgingProperties/Schemas/LodgingPropertyForm.php app/Filament/Resources/Rooms/Schemas/RoomForm.php
git commit -m "feat: hide sources from lodging setup forms"
```

### Task 3: Database Default Regression and Final Verification

**Files:**
- Modify: `tests/Feature/Panel/LodgingModelsTest.php`

- [ ] **Step 1: Add explicit assertions for the existing database defaults**

In `test_property_room_and_reservation_relations()`, immediately after creating `$property` and `$room`, add:

```php
$this->assertSame('manual', $property->source);
$this->assertSame('manual', $room->source);
```

These regression assertions document why removing the form fields does not require model hooks or schema changes.

- [ ] **Step 2: Run lodging model and panel tests**

Run:

```powershell
php artisan test tests/Feature/Panel/LodgingModelsTest.php tests/Feature/Panel/LodgingPanelTest.php tests/Feature/Panel/LodgingSourceSelectTest.php
```

Expected: PASS with no failures or errors.

- [ ] **Step 3: Format changed PHP files**

Run:

```powershell
vendor/bin/pint app/Filament/Resources/LodgingProperties/Schemas/LodgingPropertyForm.php app/Filament/Resources/Rooms/Schemas/RoomForm.php app/Filament/Resources/LodgingReservations/Schemas/LodgingReservationForm.php tests/Feature/Panel/LodgingSourceSelectTest.php tests/Feature/Panel/LodgingModelsTest.php
```

Expected: Pint completes successfully.

- [ ] **Step 4: Run the complete test suite**

Run:

```powershell
php artisan test
```

Expected: all tests pass with no failures or errors.

- [ ] **Step 5: Commit regression coverage and any formatting changes**

```powershell
git add -- app/Filament/Resources/LodgingProperties/Schemas/LodgingPropertyForm.php app/Filament/Resources/Rooms/Schemas/RoomForm.php app/Filament/Resources/LodgingReservations/Schemas/LodgingReservationForm.php tests/Feature/Panel/LodgingSourceSelectTest.php tests/Feature/Panel/LodgingModelsTest.php
git commit -m "test: cover lodging source defaults"
```

