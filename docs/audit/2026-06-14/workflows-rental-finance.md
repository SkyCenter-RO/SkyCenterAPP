# Vehicle Rental, Payments, Budget, and Telegram Bot Workflow Audit

This document contains the workflow trace and gap analysis for the Vehicle Rental, Payments, Budget, and Telegram bot domains, conducted on June 16, 2026, on branch `audit/full-application` in the worktree `D:\Automation\SkyPark\App\.worktrees\full-application-audit`.

---

## 1. Vehicle Rental Workflow Trace

### Workflow Definition

| Step | Detail |
| :--- | :--- |
| **Trigger** | Manual contract or maintenance registration in the Filament panel. |
| **Validation** | Basic form validation (required status, numbers, dates). No date-range checks. |
| **Persistence** | Writes to `rent_vehicles`, `rent_clients`, `rent_contracts`, and `rent_maintenance_records`. |
| **State Transition** | Vehicle statuses are purely manual (e.g., `'available'`, `'rented'`, `'maintenance'`). |
| **Audit Record** | None. |
| **Retry Behavior** | N/A (Manual only). |
| **Existing Test** | `tests/Feature/Panel/RentModelsTest.php`, `tests/Feature/Panel/RentPanelTest.php`, `tests/Feature/Schema/RentSchemaTest.php`. |
| **Gaps & Issues** | Overlap check missing (active contracts can overlap for the same vehicle on the same dates). A vehicle can also be rented while in an active maintenance window. |

---

## 2. Payments Workflow Trace

### Workflow Definition

| Step | Detail |
| :--- | :--- |
| **Trigger** | Manual entry in the Filament panel or webhook updates. |
| **Validation** | Required fields, numeric checks. |
| **Persistence** | Writes to the `payments` table. |
| **State Transition** | Payments track linked reservations and contracts. |
| **Audit Record** | **None**. The `payment_change_audits` table exists but is never written by model events or observers (only manually written in test fixtures). |
| **Retry Behavior** | N/A. |
| **Existing Test** | `tests/Feature/Panel/PaymentModelsTest.php`, `tests/Feature/Schema/PaymentSchemaTest.php`. |
| **Gaps & Issues** | Missing automatic audit logs (SC-AUD-009). Currency values are not checked against list bounds in forms. |

---

## 3. Telegram Budget Bot State Machines

The Telegram interface uses two distinct state machines (income and expense) to ingest transactions via an n8n webhook.

### Income state machine:
- **selecting_service**: User selects service type (`service:parking`, `service:hotel`, `service:rent`).
- **waiting_plate**: (Parking only) User enters license plate.
- **selecting_property**: (Hotel only) User selects property (`property:skycenter`, `property:serafim`).
- **selecting_rooms**: (Hotel only) User selects room codes and confirms.
- **waiting_rent_desc**: (Rent only) User enters contract/vehicle details.
- **waiting_amount**: User enters amount (validated by `/^\d{1,8}(\.\d{1,2})?$/`).
- **selecting_payment**: User selects payment method (`method:cash`, `method:card`, `method:transfer`). On selection, writes `BudgetRawMessage`, writes `BudgetTransaction`, and deletes the session.

### Expense state machine:
- **selecting_category**: User selects expense category (`category:<id>`) or select custom (`category:custom`).
- **waiting_custom_desc**: (Custom only) User enters custom description.
- **waiting_expense_amount**: User enters expense amount. On message receipt, writes `BudgetRawMessage`, writes `BudgetTransaction`, and deletes the session.

---

## 4. Confirmed Finance & Telegram Findings

### SC-AUD-014: Missing Vehicle Availability Overlap Checks
- **Severity**: High
- **Class**: `confirmed-defect`
- **Domain**: Vehicle rental
- **Title**: Rental contracts and maintenance records permit overlapping vehicle allocations
- **Evidence**: `app/Filament/Resources/RentContracts/Schemas/RentContractForm.php` and `app/Filament/Resources/RentMaintenanceRecords/RentContractForm.php` (no overlap validations).
- **Reproduction**: Create a rental contract for Vehicle A from July 1 to July 10. Create a second contract for the same Vehicle A from July 5 to July 15. Both contracts save successfully.
- **Impact**: Double-allocation of vehicles, rent code collision, and scheduling conflicts.
- **Recommendation**: Implement form validation checking that a vehicle is not assigned to any overlapping active contract or maintenance record.

### SC-AUD-015: Inconsistent Telegram Transaction DB States (Orphan Messages)
- **Severity**: Medium
- **Class**: `confirmed-defect`
- **Domain**: Budget and Telegram
- **Title**: Telegram bot finalizes transactions without database transaction wrapping
- **Evidence**: `app/Actions/Telegram/ProcessIncomeTelegramUpdate.php:219-240`, `app/Actions/Telegram/ProcessExpenseTelegramUpdate.php:111-130` (writes `BudgetRawMessage` first, then writes `BudgetTransaction` in separate queries).
- **Reproduction**: Trigger a final bot save where a database constraint error occurs during transaction insertion (e.g. invalid foreign key). The raw message is saved, but the transaction fails, leaving the bot session active.
- **Impact**: Inconsistent financial records. Raw messages are logged, but the budget ledger is not updated, requiring manual ledger reconciliations.
- **Recommendation**: Wrap the raw message insertion, transaction creation, and session deletion in a `DB::transaction()` block.
