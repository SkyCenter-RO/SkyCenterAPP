# Cross-Domain Interconnection Matrix

This document maps the dependencies, triggers, side effects, transaction boundaries, and failure propagation behaviors across the different application domains of SkyCenter, conducted on June 16, 2026, on branch `audit/full-application` in the worktree `D:\Automation\SkyPark\App\.worktrees\full-application-audit`.

---

## 1. Interconnection Matrix

| Connection / Flow | Trigger | Target Domain | Expected State Change | Side Effects | Transaction Boundary | Retry Owner | Failure Visibility | Test Evidence | Result |
| :--- | :--- | :--- | :--- | :--- | :---: | :--- | :--- | :--- | :---: |
| **Reservation -> Customer (Parking)** | Webhook payload / manual panel entry | `ParkingCustomer` | Customer created or matched (via normalized phone) and saved. | None | **None** | n8n / Webhook | Webhook HTTP 500 error; no admin alert. | `AutomationParkingReservationWebhookTest` | **pass** |
| **Reservation -> Location** | Webhook payload / manual select | `ParkingLot` / `Room` | Reservation links to lot/room ID. | None | N/A | n8n / Operator | 500 constraint error (if invalid). | `ParkingModelsTest` / `LodgingModelsTest` | **pass** |
| **Reservation -> Payment** | Manual Payment entry in Filament | `Payment` | Payment links to reservation or contract. | None | **None** | Operator | Payments list table. | `PaymentModelsTest` | **pass** |
| **Reservation -> Observer -> Audit** | Reservation status update | `ParkingStatusAudit` / `PaymentChangeAudit` | Audit record created with status history. | None | **None** | Laravel hook | **Silent failure** (never executed in code). | None (Tests manually write rows) | **fail** (SC-AUD-009) |
| **Reservation -> Message** | Status transitions to `'booked'` | `OutboundMessage` | Pending outbound message created. | `AutomationEvent` created. | **None** | Laravel Observer | `AutomationEvent` log. | `ConfirmationMessageTest` | **pass** |
| **Telegram -> Session -> Category -> Message -> Transaction** | User completes wizard message | `TelegramSession` -> `BudgetTransaction` | Session deleted; raw message and transaction created. | Telegram response sent. | **None** | Telegram / n8n | Session remains active or gets stuck. | `IncomeTelegramWizardTest` | **fail** (SC-AUD-015, SC-AUD-011) |
| **Schedule-upload -> Work-shift -> Dashboard** | PDF schedule upload | `WorkShift` | Replaces shifts on those dates. | Updates Ordinea de Zi page. | **None** | Admin | Admin success/failure flash notification. | `OrdineaDeZiUploadTest` | **pass** |
| **Review-dispatch -> Reservation -> Outbound-message** | API call to `/dispatch-review-requests` (cron) | `OutboundMessage` | Queues review request message and marks `review_request_sent = true`. | None | **None** | n8n Scheduler | `AutomationEvent` skipped log. | `AutomationDispatchReviewRequestsTest` | **pass** |
| **User-role -> Resource -> API** | Route/Panel access request | `Authenticate` / `canAccess` | User is allowed or gets 403. | None | N/A | User | 403 Forbidden page. | `RoleAccessTest` / `MarketingResourcesAccessTest` | **fail** (SC-AUD-004, SC-AUD-005) |

---

## 2. Failure Propagation Analysis

### Webhook Flow (Parking/Lodging)
- **Partial Failure Scenario**: If the `ParkingReservation` fails to save after the `ParkingCustomer` has been successfully persisted, the customer record remains in the database (dirty write) while the webhook returns a `500` response. n8n will retry, which will match the existing customer and attempt to save the reservation again.
- **Retry Handling**: Idempotency checks on the unique index `['source', 'external_id']` ensure that retries do not duplicate reservations.
- **Recovery**: Operators cannot view webhook failure details in the Filament panel; failures must be inspected via server logs or n8n dashboard execution records.

### Telegram State Machine
- **Partial Failure Scenario**: The raw message is written to `budget_raw_messages` before the `BudgetTransaction` is inserted. If transaction insertion fails (e.g. database foreign key issue), the session remains active, and the raw message is left orphan. If the user clicks the button again, n8n retries, duplicating the raw message.
- **Recovery**: No automated recovery. The session hangs until it expires (30 minutes) and deletes itself, leaving the orphan message.

### Scheduling PDF Import
- **Partial Failure Scenario**: The PDF import iterates through lines and calls `updateOrCreate` directly. If the parsing fails on line 15, the shifts from lines 1 to 14 remain committed in the database, while the rest are missing, leaving the schedule half-imported.
- **Recovery**: The administrator must fix the PDF and re-upload it, which overwrites the existing shifts due to `updateOrCreate` on unique keys.
