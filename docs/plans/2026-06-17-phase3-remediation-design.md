# Design: Phase 3 Remediation (Low Priority Findings)

## 1. Objectives
This design document outlines the technical implementation for Phase 3 of the audit remediation plan. Phase 3 addresses low-priority maintainability and usability findings, specifically:
- **SC-AUD-003**: Larvel Pint style check errors.
- **SC-AUD-013**: Reservation status fields in Filament resources are free-form text inputs.
- **SC-AUD-016**: Filament schedule upload action lacks file size limits.
- **SC-AUD-017**: Unresolved placeholders in message templates are sent raw.

---

## 2. Proposed Design Changes

### A. Centralized Status Enums (SC-AUD-013)
We will introduce string-backed PHP enums under `app/Enums/` to define the valid states for each model.

1. **`app/Enums/ParkingReservationStatus.php`**
   - Options: `pending_approval`, `booked`, `parked`, `departed`, `cancelled`.
2. **`app/Enums/LodgingReservationStatus.php`**
   - Options: `pending`, `confirmed`, `checked_in`, `checked_out`, `cancelled`.
3. **`app/Enums/RentContractStatus.php`**
   - Options: `active`, `completed`, `cancelled`.
4. **`app/Enums/RentVehicleStatus.php`**
   - Options: `available`, `rented`, `service`.
5. **`app/Enums/SalaryStatus.php`**
   - Options: `pending`, `paid`.
6. **`app/Enums/OutboundMessageStatus.php`**
   - Options: `pending`, `sent`, `failed`, `cancelled`.
7. **`app/Enums/AutomationWebhookLogStatus.php`**
   - Options: `received`, `processed`, `error`.

We will update Eloquent models to cast `status` attributes to their respective enums:
- `ParkingReservation`
- `LodgingReservation`
- `RentContract`
- `RentVehicle`
- `Salary`
- `OutboundMessage`
- `AutomationWebhookLog`

### B. Filament Select Components (SC-AUD-013)
We will replace all `TextInput::make('status')` with `Select::make('status')->options(EnumClass::class)` in the following schema files:
- `app/Filament/Resources/ParkingReservations/Schemas/ParkingReservationForm.php`
- `app/Filament/Resources/LodgingReservations/Schemas/LodgingReservationForm.php`
- `app/Filament/Resources/RentContracts/Schemas/RentContractForm.php`
- `app/Filament/Resources/RentVehicles/Schemas/RentVehicleForm.php`
- `app/Filament/Resources/Salaries/Schemas/SalaryForm.php`
- `app/Filament/Resources/OutboundMessages/Schemas/OutboundMessageForm.php`
- `app/Filament/Resources/AutomationWebhookLogs/Schemas/AutomationWebhookLogForm.php`

### C. Schedule Uploader Size Limit (SC-AUD-016)
In `app/Filament/Pages/OrdineaDeZi.php`, we will add `->maxSize(5120)` to `FileUpload::make('schedule_pdf')`. This enforces a 5MB limit client-side and server-side during the Filament request cycle.

### D. Message Template Placeholder Sanitization (SC-AUD-017)
In `app/Actions/Messaging/RenderMessageTemplate.php`, we will add a fallback safety replace step to remove unresolved double-curly brace placeholders (e.g. `{{guest_name}}`) using a regex pattern:
```php
$text = preg_replace('/\{\{[^}]*\}\}/', '', $text);
```

### E. Code Style Formatting (SC-AUD-003)
We will run `vendor/bin/pint` to mechanically format and resolve code style violations across the entire repository.

---

## 3. Verification Plan

### Automated Tests
1. **Placeholder Sanitization Test**:
   - Write a test in `RenderMessageTemplateTest` that creates a template with placeholders, calls `handle` with a payload missing one of the placeholders, and asserts that the missing placeholder is stripped from the returned text.
2. **File Size Limit Test**:
   - Add a test verifying that `FileUpload` on the `OrdineaDeZi` page contains the `maxSize` rule of `5120` KB.
3. **Status Validation Test**:
   - Write tests verifying that invalid status strings are rejected when saving/updating records on forms, and that forms only present select options from the enums.

### Manual Verification
- Log in to the Filament panel, open reservation forms, and check that the status field is a Select dropdown.
- Check that Pint returns exit code `0` on `--test`.
