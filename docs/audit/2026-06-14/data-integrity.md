# Database Integrity, Idempotency, and Concurrency Audit

This document contains the database integrity, constraint, transaction, idempotency, and concurrency assessment for SkyCenter, conducted on June 16, 2026, on branch `audit/full-application` in the worktree `D:\Automation\SkyPark\App\.worktrees\full-application-audit`.

## Schema Contracts

The database migrations define clear schema constraints for keys, types, and nullability:
- **Foreign Keys**: Cascading deletes are enforced on primary owners (`cascadeOnDelete()`), and `nullOnDelete()` is used for audit and non-ownership links (e.g., `created_by_id`, `updated_by_id`, `webhook_log_id`).
- **Unique Constraints**: Unique indexes exist for natural keys (e.g. `email` on users, `['source', 'external_id']` on reservations and clients, and `['date', 'shift_type']` on work shifts).
- **Check Constraints**: Check constraints exist in migrations to enforce valid status values for enums, validated via feature tests.

---

## Model and Form Consistency

- **Casts and Formats**: Model casts (like `amount => 'decimal:2'` and datetime casts) match the migration types.
- **Form Validation Gaps**: Filament schemas validate required fields, numbers, and basic formats. However, model-level validation is absent, meaning CLI operations (e.g., Tinker) or direct API database writes bypass Filament validation.
- **Audit Divergence**: The database schemas and tests contain tables for `payment_change_audits` and `parking_status_audits`. However, the application code contains no observers or actions that automatically populate these audits; the tests pass only by manually inserting audit rows. This represents a missing workflow.

---

## Transaction Boundaries

The codebase lacks database transaction wrapping (`DB::transaction`) for multi-step write operations:
1. **Webhook Processing**: In `UpsertParkingReservationFromWebhook` and `UpsertLodgingReservationFromWebhook`, customer and reservation records are upserted, and an `AutomationEvent` is created. If event creation fails, the customer/reservation persist in a partially processed state.
2. **Telegram Bot Actions**: In `ProcessIncomeTelegramUpdate` and `ProcessExpenseTelegramUpdate`, a `BudgetRawMessage` is inserted, then a `BudgetTransaction` is created, and finally the `TelegramSession` is deleted. If any later step fails, the raw message or transaction persists without rolling back the earlier steps, creating database drift.
3. **Scheduling PDF Import**: In `ParseSchedulePdfAction`, daily work shifts are imported in a loop using `updateOrCreate`. If a parsing error occurs mid-document, the shifts processed up to that line remain saved in the database instead of rolling back the entire import.

---

## Idempotency

- **Webhooks**: Idempotency is partially achieved via the `['source', 'external_id']` unique index and Eloquent's `first()` check or `updateOrCreate`. Repeated webhook deliveries update the existing record rather than creating duplicates.
- **Telegram Updates**: No idempotency checks are performed on Telegram message or callback IDs. Repeated webhook notifications from Telegram (which happen if n8n retries the request) will re-execute the final state handler, creating duplicate raw messages and transactions.
- **PDF Uploads**: Uploading the same PDF multiple times updates the existing shifts instead of duplicating them, due to the unique key on `['date', 'shift_type']`.

---

## Concurrency

The application does not implement database locking (e.g. `lockForUpdate()`) or optimistic concurrency controls:
1. **Concurrent Webhooks**: If two webhook payloads with the same external ID are processed concurrently, both will query the database (finding no record) and attempt to insert. This relies on the database unique index to crash one of the threads with a unique constraint violation (500 error) instead of handling it gracefully.
2. **Telegram State Transitions**: Since sessions are not locked, concurrent callback clicks by a user can lead to parallel threads executing the same state, causing duplicate budget transactions to be created before the session is deleted.
3. **Outbound Callbacks**: In `OutboundMessageCallbackController`, concurrent callbacks for the same message will pass the `status === 'pending'` check in both threads, inserting duplicate webhook logs and automation events.

---

## Deletion and Retention

- **Cascading Deletes**: Deleting a `ParkingLot` deletes all its `ParkingZones`, which in turn deletes associated spaces. `LodgingProperty` deletion cascades to its `Rooms`.
- **Soft Deletes**: Soft deletes are not configured on any operational models (e.g. reservations, contracts, client details), meaning operator deletes are permanent and irrecoverable.
- **Data Retention**: No cleanup tasks or retention policies exist for temporary uploads, raw message logs, or automation webhook logs.

---

## Migration and Rollback Risk

- **Rollbacks**: The migration files define complete `down()` methods, enabling clean schema rollbacks.
- **Seeders**: Seeders run successfully, but the `OperatorUserSeeder` does not check the environment, meaning running seeds in production will write hardcoded operator accounts with default credentials.

---

## Confirmed Findings

### SC-AUD-008: Missing Transaction Boundaries on Database Write Loops
- **Severity**: Medium
- **Class**: `confirmed-defect`
- **Domain**: System
- **Title**: Multi-step writes and import loops are executed without database transactions
- **Evidence**: `app/Actions/Scheduling/ParseSchedulePdfAction.php:67-81`, `app/Actions/Telegram/ProcessIncomeTelegramUpdate.php:219-240`, `app/Actions/Automation/UpsertParkingReservationFromWebhook.php:31-64`.
- **Reproduction**: Trigger a PDF schedule upload where an operator's name is misspelled or invalid on day 15. The first 14 days will be written to the database, while the remaining days fail, leaving the database in a partially updated state.
- **Impact**: Inconsistent database states, orphaned records, and partial schedule imports.
- **Recommendation**: Wrap multi-step writes (such as webhook processing, Telegram budget updates, and PDF import loops) in `DB::transaction()` blocks.

### SC-AUD-009: Missing Automated Audit Logging for Payments and Parking Status
- **Severity**: Medium
- **Class**: `missing-workflow`
- **Domain**: System
- **Title**: Payment change audits and parking reservation status audits are never automatically generated
- **Evidence**: `app/Models/Payment.php:40` and `app/Models/ParkingReservation.php:61` define the relationships, but no model events, observers, or listeners write to the audit tables.
- **Reproduction**: Update the status of a parking reservation from 'booked' to 'arrived' in the Filament panel. Check the `parking_status_audits` table; no new audit row is created.
- **Impact**: Compliance and audit failures. Financial changes and reservation status histories are not audited automatically, leaving the history untracked.
- **Recommendation**: Register model observers or use Eloquent model boot events (e.g., `saved`, `updated`) to automatically write to `payment_change_audits` and `parking_status_audits`.

### SC-AUD-010: Missing Booking Availability Checks (Double-Booking Vulnerability)
- **Severity**: High
- **Class**: `confirmed-defect`
- **Domain**: Lodging / Vehicle rental
- **Title**: Lodging rooms and vehicles can be double-booked for overlapping periods
- **Evidence**: `app/Filament/Resources/LodgingReservations/Schemas/LodgingReservationForm.php` and `app/Filament/Resources/RentContracts/Schemas/RentContractForm.php` contain no date overlap validation.
- **Reproduction**: Create a lodging reservation for Room A from June 20 to June 25. Create a second lodging reservation for the same Room A from June 22 to June 28. Both reservations save successfully.
- **Impact**: Double-booking of rooms and vehicles, resulting in operational disruptions, client disputes, and manual correction overhead.
- **Recommendation**: Add validation rules on lodging and rental forms to verify that the selected room/vehicle has no overlapping active bookings on the selected date range.

### SC-AUD-011: Concurrency Race Conditions in Webhook and Telegram Handlers
- **Severity**: High
- **Class**: `security-risk`
- **Domain**: System
- **Title**: Handlers do not lock database rows, allowing duplicate records under concurrent requests
- **Evidence**: Absence of `lockForUpdate()` or pessimistic locks in `UpsertParkingReservationFromWebhook.php`, `ProcessIncomeTelegramUpdate.php`, and `OutboundMessageCallbackController.php`.
- **Reproduction**: Send concurrent duplicate callback requests to the Telegram income webhook or outbound callback route. Both requests execute in parallel, creating duplicate transactions or events.
- **Impact**: Duplicate financial transactions in the budget, duplicate logs, and database locking errors.
- **Recommendation**: Use database transactions combined with pessimistic locking (`lockForUpdate()`) on the `TelegramSession` or check for transaction existence before creation.
