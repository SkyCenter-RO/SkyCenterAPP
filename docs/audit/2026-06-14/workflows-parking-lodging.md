# Parking and Lodging Business Workflow Audit

This document contains the workflow trace and gap analysis for the Parking and Lodging domains, conducted on June 16, 2026, on branch `audit/full-application` in the worktree `D:\Automation\SkyPark\App\.worktrees\full-application-audit`.

---

## 1. Parking Reservation Workflow Trace

### Workflow Definition

| Step | Detail |
| :--- | :--- |
| **Trigger** | Webhook payload received at `POST api/automation/parking-reservations` or manually entered in the Filament panel. |
| **Validation** | Webhook: checks for `event_type === 'unparsed'` and missing `external_id`, `check_in_at`, `check_out_at`. Panel: basic Filament field rules. |
| **Persistence** | Upserts a `ParkingCustomer` (matched by normalized phone) and a `ParkingReservation` (matched by `source` and `external_id`). Creates `AutomationEvent` (`reservation_created` or `reservation_updated`) and updates `AutomationWebhookLog`. |
| **State Transition** | New webhooks default to `status = 'pending_approval'`. Updates preserve existing status. Filament panel allows manual text status updates. |
| **Audit Record** | None. The `parking_status_audits` table is not populated by the application (missing observer/listeners). |
| **Message Side Effect** | `ParkingReservationObserver` listens to Eloquent save events. When `status` transitions to `'booked'`, it queues a confirmation `OutboundMessage` and records an event. |
| **Retry Behavior** | Idempotent on reservation level (updates the existing record), but inserts duplicate webhook logs and automation events. |
| **Existing Test** | `tests/Feature/Api/AutomationParkingReservationWebhookTest.php`, `tests/Feature/Panel/ParkingModelsTest.php`, `tests/Feature/Panel/ParkingPanelTest.php`. |
| **Gaps & Issues** | Plate normalization is skipped in webhooks; `normalized_plate` is only filled via manual input in Filament. Status fields are free-form text. Lack of automated status logging. |

---

## 2. Lodging Reservation Workflow Trace

### Workflow Definition

| Step | Detail |
| :--- | :--- |
| **Trigger** | Webhook payload received at `POST api/automation/lodging-reservations` or manually entered in the Filament panel. |
| **Validation** | Webhook: checks for `event_type === 'unparsed'` and missing `source`, `external_id`, `check_in`, `check_out`. Panel: basic Filament field rules. |
| **Persistence** | Upserts a `LodgingReservation` (matched by `source` and `external_id`). Creates `AutomationEvent` (`reservation_created` or `reservation_updated`) and updates `AutomationWebhookLog`. |
| **State Transition** | New webhooks default to `status = 'pending'`. Room assignment (`room_id`) is left null and must be assigned manually. |
| **Audit Record** | None. Lodging domain has no audit table or status change listeners. |
| **Message Side Effect** | `LodgingReservationObserver` listens to Eloquent save events. When `status` transitions to `'booked'`, it queues a confirmation `OutboundMessage` and records an event. |
| **Retry Behavior** | Idempotent on reservation level (updates the existing record), but inserts duplicate webhook logs and automation events. |
| **Existing Test** | `tests/Feature/Api/AutomationLodgingReservationWebhookTest.php`, `tests/Feature/Panel/LodgingModelsTest.php`, `tests/Feature/Panel/LodgingPanelTest.php`. |
| **Gaps & Issues** | Double-booking vulnerability (no check on whether the room is already assigned to an overlapping reservation). Status fields are free-form text. |

---

## 3. Review Request Dispatch Workflow Trace

### Workflow Definition

| Step | Detail |
| :--- | :--- |
| **Trigger** | Webhook payload received at `POST api/automation/dispatch-review-requests` (invoked on a schedule by n8n). |
| **Validation** | Bearer token verified via `automation.token` middleware. |
| **Persistence** | For each eligible reservation, creates a pending `OutboundMessage` of type `review_request`, updates `review_request_sent = true` on the reservation, and logs an `AutomationEvent` (`review_request_queued`). |
| **State Transition** | `review_request_sent` transitions from `false` to `true`. |
| **Audit Record** | None. |
| **Message Side Effect** | Renders templates via `RenderMessageTemplate` and schedules messages. |
| **Retry Behavior** | Safe. Already processed records (`review_request_sent = true`) are skipped. |
| **Existing Test** | `tests/Feature/Api/AutomationDispatchReviewRequestsTest.php`. |
| **Gaps & Issues** | If template rendering fails or user contact details (phone/email) are missing, the event is marked `skipped` silently. No administrative warning is generated. |

---

## 4. Confirmed Workflow Findings

### SC-AUD-012: Inconsistent Plate Normalization in Parking Webhook
- **Severity**: Medium
- **Class**: `confirmed-defect`
- **Domain**: Parking
- **Title**: Normalized plate is not populated when parking reservations are created via webhook
- **Evidence**: `app/Actions/Automation/UpsertParkingReservationFromWebhook.php:39-53` (does not compute or fill `normalized_plate`).
- **Reproduction**: Send a valid parking webhook payload with `plate => 'B 123 ABC'`. Inspect the database record; `normalized_plate` remains `null`.
- **Impact**: Database query inconsistency. If administrators query by `normalized_plate` in the panel or automated scripts, webhook-created records will be missed.
- **Recommendation**: In `UpsertParkingReservationFromWebhook.php`, sanitize and populate `normalized_plate` (e.g. uppercase alphanumeric characters only) when the plate is set or updated.

### SC-AUD-013: Free-Form Text Input for Reservation Statuses
- **Severity**: Low
- **Class**: `maintainability`
- **Domain**: Parking / Lodging
- **Title**: Reservation status fields in Filament resources are free-form text inputs
- **Evidence**: `app/Filament/Resources/ParkingReservations/Schemas/ParkingReservationForm.php:30` (`TextInput::make('status')`), `app/Filament/Resources/LodgingReservations/Schemas/LodgingReservationForm.php:32` (`TextInput::make('status')`).
- **Reproduction**: Access the Filament edit page for any reservation, type `"invalid_status"` into the Status field, and save. The change is persisted successfully.
- **Impact**: Inconsistent data states. Operators can easily misspell statuses (e.g. `"bookd"` instead of `"booked"`), which prevents observers from sending confirmation messages and causes scheduling or review dispatch scripts to skip these bookings.
- **Recommendation**: Replace `TextInput` status fields in Filament with `Select` dropdowns containing defined status enums/options.
