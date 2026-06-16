# Workflow Status and Defect Classification

This document classifies all findings from the SkyCenter audit of June 16, 2026, separating **Confirmed Defects** (broken implementations of existing requirements) from **Missing Workflows / Gaps** (entirely missing business processes or infrastructure components).

---

## 1. Confirmed Defects

A **Confirmed Defect** represents a failure of the code to correctly or safely execute its current logic. These are bugs that violate data integrity or cause incorrect states.

| Finding ID | Severity | Title | Impact | Root Cause |
| :--- | :---: | :--- | :--- | :--- |
| **SC-AUD-008** | **Medium** | Multi-step writes and import loops lack database transactions | Partial database updates leave orphan records on parser crashes. | Missing `DB::transaction` wrapping. |
| **SC-AUD-010** | **High** | Lodging rooms can be double-booked | Multiple active reservations can overlap in the same room. | Absence of date-range overlap database/form constraints. |
| **SC-AUD-012** | **Medium** | `normalized_plate` is not populated on webhook parking reservations | Webhook reservations bypass normalized lookup columns. | Webhook controller does not populate `normalized_plate`. |
| **SC-AUD-014** | **High** | Vehicles can be double-booked or allocated to concurrent maintenance | Same vehicle can be assigned to multiple active contracts or service slots. | Absence of date-range overlap constraints in Rent module. |
| **SC-AUD-015** | **Medium** | Telegram bot finalizes transactions without database transactions | Orphaned raw messages are saved without budget transactions on failure. | Wizard state finalize action writes to tables sequentially without transaction wrapper. |

---

## 2. Missing Workflows & Gaps

A **Missing Workflow** or **Operational/Security Gap** represents a feature, protection, or process that was never implemented in the application but is necessary for secure, stable business operations.

### A. Core Business Gaps
- **SC-AUD-009 (Missing Workflow)**: *Automatic Payment/Parking Status Audit Trail Generation*. While the auditing database tables exist, they are never populated by model events, observers, or saves.
- **SC-AUD-018 (Missing Workflow/Documentation Gap)**: *Marketing Workflow Automation*. The Marketing domain is purely CRUD storage (no active campaign emails, SMS, or automation are connected).
- **SC-AUD-027 (Missing Workflow/Operational Gap)**: *Automated Database Backup Pipeline*. No automated process is configured to backup database states offsite.

### B. Security Gaps (OWASP ASVS Gaps)
- **SC-AUD-004**: *Missing Eloquent Policies*. Operators can run destructive edits/deletes on reservations and contracts because no authorization checks are registered on models.
- **SC-AUD-005**: *Missing Resource canAccess() Checks*. Operators can access system configurations, log views, and outgoing SMS templates.
- **SC-AUD-006**: *Missing API Rate Limiting*. Public endpoints lack throttling, leaving them open to DoS/brute force.
- **SC-AUD-007**: *Hardcoded default operator password*. Predictable initial operator passwords across all environments.
- **SC-AUD-011**: *Missing Concurrent Row Locking*. Handlers lack pessimistic row locks (`lockForUpdate`), risking duplicate budget records under concurrent webhook or Telegram hits.
- **SC-AUD-016**: *Missing file size validation limits on PDF imports*. Uploading large PDFs can exhaust server memory.

### C. Operational & Build Gaps
- **SC-AUD-001**: *Missing npm lockfile*. Reproducible frontend builds are impossible in CI.
- **SC-AUD-023**: *Missing Automated Queue Worker*. Queued jobs are never executed automatically because no worker daemon is defined in Compose.
- **SC-AUD-025**: *Missing Schedule Runner Daemon*. Future cron jobs will not run automatically.
- **SC-AUD-028**: *Rent contracts require manual DB integer ID inputs*. Lack of searchable Select relation drop downs makes the client/vehicle selection unusable for operators.
- **SC-AUD-029**: *Editable Normalization Fields*. Normalization inputs are exposed, risking lookup database desyncs.
