# Prioritized Remediation Backlog

This document presents the risk-ranked prioritization and implementation plan for the 36 findings identified during the SkyCenter application audit completed on June 16, 2026.

---

## 1. Summary of Findings by Severity

| Severity | Count | Primary Impact Area | Focus Area |
| :--- | :---: | :--- | :--- |
| **Critical** | 1 | Background operations stalled | Automated queue runner configuration. |
| **High** | 9 | Data integrity / Privilege bypass | Eloquent Policies, double-booking prevention, row locks, production web server, searchable Select relations, and CI quality gates. |
| **Medium** | 13 | Reliability / Validation | DB transaction wrapping, API rate limiters, seeder security, automatic audit observers, webhook data normalization, and static analysis. |
| **Low** | 13 | Maintainability / Usability | Pint style fixes, Filament select dropdowns, upload size limits, template placeholder sanitization, and documentation. |

---

## 2. Phase 1: Critical & High Priority Gaps (Immediate Resolution)

### Backlog Item 1: Enable Automated Queue worker daemon
- **Findings**: **SC-AUD-023** (Critical)
- **Remediation**: Add a supervised queue worker container running `php artisan queue:work` to `docker-compose.yml`.
- **Acceptance Criteria**: Queued notification jobs process automatically in the background without manual CLI execution.
- **Verification**: Queue a job via webhook, check database `jobs` table is automatically cleared, and logs confirm job completion.

### Backlog Item 2: Implement Eloquent Policies and Resource Access Limits
- **Findings**: **SC-AUD-004**, **SC-AUD-005** (High)
- **Remediation**: 
  - Create Policies in `app/Policies/` for all operational models (`ParkingReservation`, `LodgingReservation`, `RentContract`, `BudgetTransaction`, etc.).
  - Restrict resource access by implementing `canAccess()` rules on system resources (`AutomationWebhookLogs`, `MessageTemplates`, `OutboundMessages`).
- **Acceptance Criteria**: Operator users receive `403 Forbidden` responses when requesting delete/edit actions or visiting system panel routes.
- **Verification**: Run `php artisan test --filter=RoleAccessTest` and verify that operator requests to restricted resources return HTTP status `403`.

### Backlog Item 3: Prevent Overlapping Lodging Rooms and Vehicle Rentals
- **Findings**: **SC-AUD-010**, **SC-AUD-014** (High)
- **Remediation**: Implement form validation rules and database checks verifying that new reservations or maintenance records do not overlap with existing bookings.
- **Acceptance Criteria**: Creating a reservation or rent contract for an occupied vehicle/room date range throws validation errors.
- **Verification**: Write integration tests trying to save overlapping date records, and assert that validation fails.

### Backlog Item 4: Apply Pessimistic Database Row Locking
- **Findings**: **SC-AUD-011** (High)
- **Remediation**: Use `lockForUpdate()` during webhook ingestion and Telegram session updates to prevent concurrent request race conditions.
- **Acceptance Criteria**: Simultaneous webhook/bot updates process sequentially, rejecting or skip-handling duplicate rows.
- **Verification**: Execute concurrent mock request threads and verify that only a single transaction/event is persisted.

### Backlog Item 5: Transition from `php artisan serve` to FrankenPHP or Nginx + PHP-FPM
- **Findings**: **SC-AUD-024** (High)
- **Remediation**: Replace the PHP CLI serve command in the Dockerfile with a hardened, multi-threaded production-grade server configuration.
- **Acceptance Criteria**: Web requests are served by a hardened multi-threaded server.
- **Verification**: Run a load test (e.g. using ApacheBench or wrk) with concurrency > 10 and verify zero dropped requests.

### Backlog Item 6: Replace Manual ID inputs with searchable Select pickers
- **Findings**: **SC-AUD-028** (High)
- **Remediation**: Update `RentContractForm` to use searchable Filament Select relations for `rent_vehicle_id` and `rent_client_id`.
- **Acceptance Criteria**: Operators can select vehicles by license plate and clients by name via search dropdowns.
- **Verification**: Open the rent contract page, search for a client/vehicle, and verify selections are saved correctly.

### Backlog Item 7: Build Automated CI/CD Pipelines
- **Findings**: **SC-AUD-033** (High)
- **Remediation**: Create `.github/workflows/ci.yml` compiling assets, running tests, linting, and checking security.
- **Acceptance Criteria**: Commits and pull requests automatically trigger the test suite, style checks, and asset build.
- **Verification**: Push a branch and verify that the GitHub Action checks run and succeed.

---

## 3. Phase 2: Medium Priority (Reliability & Robustness)

### Backlog Item 8: Database Transaction Wrapping
- **Findings**: **SC-AUD-008**, **SC-AUD-015** (Medium)
- **Remediation**: Wrap all loops, webhook actions, and wizard finalizations inside `DB::transaction()` blocks.
- **Acceptance Criteria**: If any database write step fails, all preceding insertions are rolled back cleanly.
- **Verification**: Inject a database error in the final step of the Telegram bot or webhook save, and confirm no partial/dirty records are committed.

### Backlog Item 9: API Rate Limiting and Seeder Hardening
- **Findings**: **SC-AUD-006**, **SC-AUD-007** (Medium)
- **Remediation**:
  - Apply `throttle` middleware to the api routes group.
  - Set seeder passwords via environment variables rather than hardcoded credentials.
- **Acceptance Criteria**: Requests exceeding rate thresholds return `429 Too Many Requests`. Seeders do not write hardcoded credentials to production.
- **Verification**: Send 100 requests to a webhook endpoint and confirm throttled responses. Run seeders with production config and check that default passwords are set via env.

### Backlog Item 10: Automatic Audit Trails & Normalized Webhooks
- **Findings**: **SC-AUD-009**, **SC-AUD-012** (Medium)
- **Remediation**:
  - Implement Boot events or Eloquent Observers to write status updates to audit logs automatically.
  - Ensure the webhook controller normalizes license plates before inserting records.
- **Acceptance Criteria**: Every manual status modification writes a row to the status audit tables. Webhook parking creations populate `normalized_plate`.
- **Verification**: Edit a parking reservation status, and confirm a new record is added to `parking_status_audits` automatically.

### Backlog Item 11: Static Analysis, Dependency Lock, and Backups
- **Findings**: **SC-AUD-001**, **SC-AUD-027**, **SC-AUD-034** (Medium)
- **Remediation**:
  - Commit the npm dependency lockfile.
  - Install PHPStan and configure level 5 type checking.
  - Provision a daily `pg_dump` backup script uploading archives offsite.
- **Acceptance Criteria**: Clean setups run `npm ci` without errors. PHPStan is run locally. Daily database backups run automatically.
- **Verification**: Run `npm ci`, run `vendor/bin/phpstan`, and check database cron backups.

---

## 4. Phase 3: Low Priority (Usability & Formatting)

### Backlog Item 12: Select Dropdowns for Status Fields and Pint formatting
- **Findings**: **SC-AUD-003**, **SC-AUD-013** (Low)
- **Remediation**: Replace free-form `TextInput` status fields in Filament with Select dropdowns referencing status enums. Run `vendor/bin/pint` to fix all style violations.
- **Acceptance Criteria**: Status inputs restrict operator saves to valid statuses only. Pint check exits 0.
- **Verification**: Form status elements are dropdown choices. Pint check passes.

### Backlog Item 13: File Size Limits and Placeholder Sanitization
- **Findings**: **SC-AUD-016**, **SC-AUD-017** (Low)
- **Remediation**: Add size limits (`maxSize(5120)`) to the schedule uploader. Clean unresolved placeholders from outgoing messages.
- **Acceptance Criteria**: Large file uploads are rejected instantly. Unresolved placeholder keys are stripped before sending messages.
- **Verification**: Attempt to upload a 50MB PDF and verify client-side rejection. Send a template with missing payload attributes, and confirm raw braces are stripped.
