# SkyCenter Full Application Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce a reproducible, evidence-based assessment of SkyCenter's production readiness, security, business workflows, integrations, test coverage, usability, and missing functionality, followed by a risk-ranked remediation backlog.

**Architecture:** The audit is documentation-first and non-destructive. Evidence is collected into focused Markdown and machine-readable inventory files under `docs/audit/2026-06-14/`, reviewed by domain, and synthesized into one findings register and final report. Production behavior is not modified during audit execution; confirmed defects move into separate TDD remediation tasks, and missing workflows move into separately approved designs.

**Tech Stack:** Laravel 12, Filament 5, PHP 8.3, PHPUnit 11, PostgreSQL 16, Docker Compose, Composer, npm/Vite, Laravel Pint, OWASP ASVS 5.0, PowerShell, Git.

---

## File Structure

The audit creates the following evidence set:

- `docs/audit/2026-06-14/README.md` - audit index, environment, scope, status, and evidence rules.
- `docs/audit/2026-06-14/baseline.md` - exact baseline commands, durations, results, and reproducibility blockers.
- `docs/audit/2026-06-14/application-inventory.md` - routes, components, models, resources, tests, configuration, and external boundaries.
- `docs/audit/2026-06-14/application-inventory.csv` - one row per auditable component for traceability.
- `docs/audit/2026-06-14/security-asvs.md` - OWASP ASVS-based control assessment and authorization matrix.
- `docs/audit/2026-06-14/data-integrity.md` - migration, constraint, transaction, idempotency, concurrency, and retention review.
- `docs/audit/2026-06-14/workflows-parking-lodging.md` - parking and lodging workflow traces.
- `docs/audit/2026-06-14/workflows-rental-finance.md` - rental, payments, budget, and Telegram workflow traces.
- `docs/audit/2026-06-14/workflows-scheduling-messaging-marketing.md` - scheduling, messaging, automation, and marketing traces.
- `docs/audit/2026-06-14/interconnections.md` - cross-domain dependency and side-effect matrix.
- `docs/audit/2026-06-14/test-traceability.md` - behavior-to-test mapping, gaps, duration, and coverage findings.
- `docs/audit/2026-06-14/operations.md` - build, deployment, queues, logging, monitoring, backup, restore, and recovery evidence.
- `docs/audit/2026-06-14/manual-usability.md` - administrator/operator manual test results.
- `docs/audit/2026-06-14/findings.csv` - normalized finding register.
- `docs/audit/2026-06-14/findings.md` - human-readable findings ordered by severity.
- `docs/audit/2026-06-14/missing-workflows.md` - missing or incomplete business capabilities separated from defects.
- `docs/audit/2026-06-14/remediation-backlog.md` - ordered, testable remediation batches.
- `docs/audit/2026-06-14/final-report.md` - executive assessment and release recommendation.
- `docs/audit/2026-06-14/ci-quality-gates.md` - proposed continuous-integration and quality gates.
- `docs/audit/2026-06-14/documentation-plan.md` - concrete replacement plan for the template README and operations documentation.

## Evidence Rules

- Record the command, timestamp, environment, exit code, duration, and relevant output for every automated check.
- Cite repository evidence with file paths and line numbers.
- Classify observations as `confirmed-defect`, `security-risk`, `operational-gap`, `missing-workflow`, `test-gap`, `maintainability`, or `documentation-gap`.
- Do not call an observation a defect unless it has a reliable reproduction or direct code-path proof.
- Do not edit application behavior during Tasks 1-15.
- Never expose values from `.env`; record variable names and whether values are set, not their contents.
- Use disposable test data and the test database only.
- Commit each completed evidence batch separately.

### Task 1: Create the Audit Workspace and Evidence Schema

**Files:**
- Create: `docs/audit/2026-06-14/README.md`
- Create: `docs/audit/2026-06-14/findings.csv`

- [ ] **Step 1: Create the audit index**

Create `README.md` with this exact structure:

```markdown
# SkyCenter Application Audit - 2026-06-14

## Scope

This directory contains the evidence and findings for the approved full-application audit defined in `docs/superpowers/specs/2026-06-14-skycenter-full-application-audit-design.md`.

## Environment

- Repository: `D:\Automation\SkyPark\App`
- Application: Laravel 12 / Filament 5 / PHP 8.3
- Database: PostgreSQL 16 through Docker Compose
- Audit mode: non-destructive; test database and disposable data only

## Finding Classes

- `confirmed-defect`
- `security-risk`
- `operational-gap`
- `missing-workflow`
- `test-gap`
- `maintainability`
- `documentation-gap`

## Severity

- Critical: unauthorized access, sensitive-data disclosure, unrecoverable data loss, corrupt financial or reservation state, or complete outage
- High: likely business disruption, privilege bypass, incorrect reservations or payments, duplicate external effects, or unreliable recovery
- Medium: limited workflow failure, weak validation, poor diagnostics, performance risk, or manual workaround
- Low: maintainability, consistency, minor usability, formatting, or documentation issue

## Evidence Status

| Area | Status | Evidence file |
|---|---|---|
| Baseline | Pending | `baseline.md` |
| Inventory | Pending | `application-inventory.md` |
| Security | Pending | `security-asvs.md` |
| Data integrity | Pending | `data-integrity.md` |
| Parking and lodging | Pending | `workflows-parking-lodging.md` |
| Rental and finance | Pending | `workflows-rental-finance.md` |
| Scheduling, messaging, and marketing | Pending | `workflows-scheduling-messaging-marketing.md` |
| Interconnections | Pending | `interconnections.md` |
| Test traceability | Pending | `test-traceability.md` |
| Operations | Pending | `operations.md` |
| Manual usability | Pending | `manual-usability.md` |
| Final synthesis | Pending | `final-report.md` |
```

- [ ] **Step 2: Create the normalized finding register**

Create `findings.csv` with this exact header:

```csv
id,severity,class,domain,title,status,evidence,reproduction,impact,recommendation,verification
```

Finding IDs use `SC-AUD-001`, `SC-AUD-002`, and so on. Status values are `open`, `accepted`, `fixed`, or `not-reproducible`.

- [ ] **Step 3: Verify the workspace files**

Run:

```powershell
Get-Content docs\audit\2026-06-14\README.md
Import-Csv docs\audit\2026-06-14\findings.csv | Measure-Object
git diff --check
```

Expected: the index renders, the CSV parses with zero data rows, and `git diff --check` exits successfully.

- [ ] **Step 4: Commit the audit workspace**

```powershell
git add docs/audit/2026-06-14/README.md docs/audit/2026-06-14/findings.csv
git commit -m "docs(audit): initialize evidence workspace"
```

### Task 2: Record the Reproducible Baseline

**Files:**
- Create: `docs/audit/2026-06-14/baseline.md`
- Modify: `docs/audit/2026-06-14/README.md`

- [ ] **Step 1: Capture immutable environment identifiers**

Run and record outputs without secret values:

```powershell
git rev-parse HEAD
git status --short
docker version --format '{{.Server.Version}}'
docker compose version
docker compose ps
docker compose exec -T app php --version
docker compose exec -T app composer --version
docker compose exec -T pgsql psql --version
```

Expected: a clean or explicitly explained worktree, running `app` and healthy `pgsql` services, PHP 8.3, Composer 2, and PostgreSQL 16 tooling.

- [ ] **Step 2: Capture dependency and route baselines**

Run:

```powershell
docker compose exec -T app composer validate --strict
docker compose exec -T app composer show --direct
docker compose exec -T app php artisan about
docker compose exec -T app php artisan route:list --except-vendor
docker compose exec -T app php artisan migrate:status
```

Expected: record exit codes and exact deviations. Do not repair failures in this task.

- [ ] **Step 3: Capture test, format, and frontend build baselines**

Run each command separately and record duration and exit code:

```powershell
docker compose exec -T app php artisan test --compact
docker compose exec -T app ./vendor/bin/pint --test
npm ci
npm run build
```

Expected baseline from discovery: 166 tests and 454 assertions pass; Pint reports 78 affected files; npm commands may fail until dependency setup is reproducible. Record current evidence instead of forcing the historical result.

- [ ] **Step 4: Write `baseline.md`**

Use sections `Repository`, `Containers`, `Runtime Versions`, `Dependencies`, `Routes and Migrations`, `Automated Tests`, `Formatting`, `Frontend Build`, `Reproducibility Blockers`, and `Baseline Findings`. Every command entry includes command text, exit code, duration, result summary, and linked finding IDs.

- [ ] **Step 5: Mark the baseline complete and commit**

Change the Baseline row in `README.md` from `Pending` to `Complete`, then run:

```powershell
git add docs/audit/2026-06-14/baseline.md docs/audit/2026-06-14/README.md docs/audit/2026-06-14/findings.csv
git commit -m "docs(audit): record application baseline"
```

### Task 3: Build the Application and Integration Inventory

**Files:**
- Create: `docs/audit/2026-06-14/application-inventory.md`
- Create: `docs/audit/2026-06-14/application-inventory.csv`
- Modify: `docs/audit/2026-06-14/README.md`

- [ ] **Step 1: Enumerate auditable components**

Run:

```powershell
rg --files app routes config database tests resources docker | Sort-Object
docker compose exec -T app php artisan route:list --except-vendor --json
rg -n "env\(|config\(" app config routes
rg -n "Http::|Mail::|Notification::|dispatch\(|Queue::|Storage::|Telegram|n8n|webhook|callback" app routes config docs
rg -n "observe\(|Observer|booted\(|creating\(|created\(|updating\(|updated\(" app
```

Expected: complete source lists for routes, configuration consumers, integration boundaries, and model side effects.

- [ ] **Step 2: Populate `application-inventory.csv`**

Use this exact header:

```csv
component_type,domain,path_or_route,responsibility,entry_point,dependencies,side_effects,authorization,test_evidence,status
```

Add one row for every non-vendor route, controller, action, observer, model, migration group, seeder, Filament resource, custom page, relation manager, middleware, and external integration boundary. Status values are `mapped`, `orphan-suspected`, `consumer-unknown`, or `spec-drift`.

- [ ] **Step 3: Write the architecture narrative**

In `application-inventory.md`, include:

- Domain component counts.
- Request paths for web panel, automation API, Telegram, uploads, and outbound callbacks.
- External boundary list and configuration variable names.
- Queue, observer, and persistence side effects.
- Orphan candidates and specification drift with file/line evidence.

- [ ] **Step 4: Validate inventory completeness**

Run:

```powershell
$routes = (docker compose exec -T app php artisan route:list --except-vendor --json | ConvertFrom-Json).Count
$inventoryRoutes = (Import-Csv docs\audit\2026-06-14\application-inventory.csv | Where-Object component_type -eq 'route').Count
"routes=$routes inventory_routes=$inventoryRoutes"
```

Expected: route counts match. Explain any intentional exclusions in `application-inventory.md`.

- [ ] **Step 5: Mark inventory complete and commit**

```powershell
git add docs/audit/2026-06-14/application-inventory.md docs/audit/2026-06-14/application-inventory.csv docs/audit/2026-06-14/README.md docs/audit/2026-06-14/findings.csv
git commit -m "docs(audit): map application components and integrations"
```

### Task 4: Audit Authentication, Authorization, and ASVS Controls

**Files:**
- Create: `docs/audit/2026-06-14/security-asvs.md`
- Modify: `docs/audit/2026-06-14/findings.csv`
- Modify: `docs/audit/2026-06-14/README.md`

- [ ] **Step 1: Trace authentication and role enforcement**

Read completely:

```powershell
Get-Content -Raw app\Models\User.php
Get-Content -Raw app\Providers\Filament\AdminPanelProvider.php
Get-Content -Raw app\Http\Middleware\AuthenticateAutomationToken.php
Get-Content -Raw config\auth.php
Get-Content -Raw config\session.php
Get-Content -Raw routes\api.php
rg -n "canAccessPanel|role|isAdmin|authorize|Policy|canView|canCreate|canEdit|canDelete" app tests
```

Record whether enforcement exists at navigation, page, action, relation-manager, API, and record-query layers.

- [ ] **Step 2: Build the authorization matrix**

Add a table to `security-asvs.md` with columns:

```text
Entry point | Anonymous | Operator | Admin | Enforcement location | Automated evidence | Result
```

Include all 84 non-vendor routes plus custom Filament actions and relation managers discovered in Task 3. Result values are `pass`, `fail`, or `not-tested`.

- [ ] **Step 3: Run security-focused automated checks**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/Panel/RoleAccessTest.php tests/Feature/Panel/PanelAccessTest.php tests/Feature/Marketing/MarketingResourcesAccessTest.php --compact
docker compose exec -T app php artisan test tests/Feature/Api --compact
docker compose exec -T app composer audit --locked
npm audit --omit=dev
```

Expected: record actual results and unresolved dependency risks. A passing role test does not close untested matrix rows.

- [ ] **Step 4: Assess ASVS control groups**

Document `pass`, `partial`, `fail`, or `not-applicable` for:

- Encoding and sanitization.
- Validation and business logic.
- Web frontend security.
- API and web service security.
- File handling.
- Authentication.
- Session management.
- Authorization.
- Self-contained tokens and automation tokens.
- Cryptography and secret management.
- Secure communication.
- Configuration.
- Data protection.
- Secure coding and architecture.
- Logging and error handling.

Every `partial` or `fail` row links to a finding or explicit evidence gap.

- [ ] **Step 5: Commit the security assessment**

```powershell
git add docs/audit/2026-06-14/security-asvs.md docs/audit/2026-06-14/findings.csv docs/audit/2026-06-14/README.md
git commit -m "docs(audit): assess security and authorization"
```

### Task 5: Audit Database Integrity, Idempotency, and Concurrency

**Files:**
- Create: `docs/audit/2026-06-14/data-integrity.md`
- Modify: `docs/audit/2026-06-14/findings.csv`

- [ ] **Step 1: Inspect schema and model contracts**

Run:

```powershell
rg -n "foreignId|references|unique\(|index\(|check|cascade|restrict|nullOnDelete|decimal|timestamp|dateTime" database\migrations
rg -n "fillable|guarded|casts\(|belongsTo|hasMany|hasOne|morph" app\Models
rg -n "DB::transaction|lockForUpdate|upsert|updateOrCreate|firstOrCreate|insertOrIgnore" app
```

Compare migration constraints, model casts, form validation, and action assumptions.

- [ ] **Step 2: Inspect test-database isolation and migration behavior**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/DatabaseIsolationTest.php tests/Feature/Schema --compact
docker compose exec -T app php artisan migrate:fresh --env=testing --seed --force
docker compose exec -T app php artisan test tests/Feature/Schema tests/Feature/Seeder --compact
```

Expected: all commands target `skycenter_app_test`. Stop immediately if output indicates the development database.

- [ ] **Step 3: Review duplicate and overlap handling**

Trace exact behavior for:

- Repeated parking and lodging webhook payloads.
- Repeated Telegram updates and wizard callbacks.
- Repeated outbound delivery callbacks.
- Overlapping lodging reservations.
- Overlapping rental contracts and maintenance windows.
- Duplicate schedule uploads.
- Payment audit creation under repeated updates.

Record whether protection is provided by database constraints, transactions, application logic, external ownership, or nothing.

- [ ] **Step 4: Write `data-integrity.md` and findings**

Use sections `Schema Contracts`, `Model and Form Consistency`, `Transaction Boundaries`, `Idempotency`, `Concurrency`, `Deletion and Retention`, `Migration and Rollback Risk`, and `Confirmed Findings`.

- [ ] **Step 5: Commit data-integrity evidence**

```powershell
git add docs/audit/2026-06-14/data-integrity.md docs/audit/2026-06-14/findings.csv
git commit -m "docs(audit): assess data integrity and concurrency"
```

### Task 6: Audit Parking and Lodging Workflows

**Files:**
- Create: `docs/audit/2026-06-14/workflows-parking-lodging.md`
- Modify: `docs/audit/2026-06-14/findings.csv`

- [ ] **Step 1: Trace parking from entry point to side effects**

Read parking routes, controller, action, observer, models, Filament forms/tables, migrations, and all parking tests. Document:

```text
Trigger | Validation | Persistence | State transition | Audit record | Message side effect | Retry behavior | Existing test | Gap
```

Cover customer matching, reservation upsert, dates, lot/zone/price references, capacity assumptions, status audit history, confirmation messages, malformed payloads, duplicates, and partial failures.

- [ ] **Step 2: Run the parking evidence suite**

```powershell
docker compose exec -T app php artisan test tests/Feature/Api/AutomationParkingReservationWebhookTest.php tests/Feature/Panel/ParkingModelsTest.php tests/Feature/Panel/ParkingPanelTest.php tests/Feature/Schema/ParkingSchemaTest.php --compact
```

Expected: record pass/fail, duration, and untested behaviors from the trace.

- [ ] **Step 3: Trace lodging from entry point to side effects**

Cover property and room relationships, source mapping, reservation upsert, arrivals/departures, review eligibility, review dispatch, outbound messages, callbacks, dashboard display, malformed payloads, duplicates, and partial failures.

- [ ] **Step 4: Run the lodging evidence suite**

```powershell
docker compose exec -T app php artisan test tests/Feature/Api/AutomationLodgingReservationWebhookTest.php tests/Feature/Api/AutomationDispatchReviewRequestsTest.php tests/Feature/Panel/LodgingModelsTest.php tests/Feature/Panel/LodgingPanelTest.php tests/Feature/Schema/LodgingSchemaTest.php --compact
```

- [ ] **Step 5: Write findings and commit**

```powershell
git add docs/audit/2026-06-14/workflows-parking-lodging.md docs/audit/2026-06-14/findings.csv
git commit -m "docs(audit): trace parking and lodging workflows"
```

### Task 7: Audit Rental, Payments, Budget, and Telegram Workflows

**Files:**
- Create: `docs/audit/2026-06-14/workflows-rental-finance.md`
- Modify: `docs/audit/2026-06-14/findings.csv`

- [ ] **Step 1: Trace vehicle rental behavior**

Inspect rental models, migrations, resources, forms, tables, relation managers, and tests. Map vehicle availability, client-contract relationships, overlapping periods, maintenance conflicts, pickup/return status, images, payment links, and Ordinea de Zi display.

- [ ] **Step 2: Trace payment and audit behavior**

Map payment create/update/delete paths, decimal and currency behavior, audit creation, actor attribution, direct model updates, role restrictions, and irreversible actions.

- [ ] **Step 3: Trace budget and Telegram state machines**

Read both `ProcessIncomeTelegramUpdate` and `ProcessExpenseTelegramUpdate` completely. Create state-transition tables with:

```text
Current state | Incoming update | Validation | Next state | Persisted records | Response action | Duplicate behavior | Cancellation behavior | Test evidence
```

Include category selection, amount/date parsing, raw-message retention, confirmation, cancellation, retries, duplicate update IDs, expired sessions, zero/negative amounts, decimal precision, and timezone boundaries.

- [ ] **Step 4: Run the focused finance suite**

```powershell
docker compose exec -T app php artisan test tests/Feature/Panel/RentModelsTest.php tests/Feature/Panel/RentPanelTest.php tests/Feature/Schema/RentSchemaTest.php tests/Feature/Panel/PaymentModelsTest.php tests/Feature/Schema/PaymentSchemaTest.php tests/Feature/Panel/BudgetModelsTest.php tests/Feature/Panel/BudgetPanelTest.php tests/Feature/Schema/BudgetSchemaTest.php tests/Feature/Telegram --compact
```

- [ ] **Step 5: Write findings and commit**

```powershell
git add docs/audit/2026-06-14/workflows-rental-finance.md docs/audit/2026-06-14/findings.csv
git commit -m "docs(audit): trace rental and financial workflows"
```

### Task 8: Audit Scheduling, Messaging, Automation, and Marketing

**Files:**
- Create: `docs/audit/2026-06-14/workflows-scheduling-messaging-marketing.md`
- Modify: `docs/audit/2026-06-14/findings.csv`

- [ ] **Step 1: Trace scheduling uploads and display**

Map upload authorization, MIME and size validation, PDF parser assumptions, malformed documents, partial parse behavior, duplicate/re-upload behavior, shift replacement, employee matching, date handling, and Ordinea de Zi display.

- [ ] **Step 2: Trace message lifecycle and automation observability**

Map template rendering, required variables, confirmation creation, queue/claim semantics, provider ownership, callback transitions, retries, terminal failure, webhook logs, automation events, and sensitive payload logging.

- [ ] **Step 3: Classify marketing capabilities**

For campaigns, channels, reviews, content calendar, and advertising spend, record whether each is `complete-workflow`, `crud-only`, `reporting-only`, or `missing-workflow`. Verify role access, relationships, date validation, status transitions, budget constraints, and external publication/collection expectations.

- [ ] **Step 4: Run focused suites**

```powershell
docker compose exec -T app php artisan test tests/Feature/Scheduling tests/Feature/Messaging tests/Feature/Actions/Messaging tests/Feature/Api/AutomationOutboundMessagesTest.php tests/Feature/Marketing --compact
```

- [ ] **Step 5: Write findings and commit**

```powershell
git add docs/audit/2026-06-14/workflows-scheduling-messaging-marketing.md docs/audit/2026-06-14/findings.csv
git commit -m "docs(audit): trace scheduling messaging and marketing"
```

### Task 9: Build the Cross-Domain Interconnection Matrix

**Files:**
- Create: `docs/audit/2026-06-14/interconnections.md`
- Modify: `docs/audit/2026-06-14/findings.csv`

- [ ] **Step 1: Build the dependency matrix**

Create one row per connection with columns:

```text
Source domain | Trigger | Target domain | Expected state change | Side effects | Transaction boundary | Retry owner | Failure visibility | Test evidence | Result
```

At minimum include reservation/customer, reservation/location, reservation/payment, reservation/observer/audit, reservation/message, Telegram/session/category/raw-message/transaction, schedule-upload/work-shift/dashboard, review-dispatch/reservation/outbound-message, and user-role/resource/API connections.

- [ ] **Step 2: Trace failure propagation**

For every connection, answer:

- What happens if persistence succeeds but the side effect fails?
- What happens if the request is retried?
- Can an operator see and recover the failure?
- Is the responsibility owned by Laravel, n8n, a provider, or a human process?

- [ ] **Step 3: Validate against code and tests**

Use `rg` references and focused test names to cite each result. Mark unsupported assumptions as `evidence-gap`, not as passing behavior.

- [ ] **Step 4: Commit interconnection evidence**

```powershell
git add docs/audit/2026-06-14/interconnections.md docs/audit/2026-06-14/findings.csv
git commit -m "docs(audit): map cross-domain interconnections"
```

### Task 10: Create Test Traceability and Coverage Analysis

**Files:**
- Create: `docs/audit/2026-06-14/test-traceability.md`
- Modify: `docs/audit/2026-06-14/application-inventory.csv`
- Modify: `docs/audit/2026-06-14/findings.csv`

- [ ] **Step 1: Inventory all tests and their claimed behavior**

Run:

```powershell
docker compose exec -T app php artisan test --list-tests
rg -n "function test|#\[Test\]|public function test|test\(" tests
```

Map each test to inventory components and classify it as unit, feature, authorization, contract, smoke, or operational.

- [ ] **Step 2: Identify behavior gaps**

Explicitly assess malformed inputs, direct unauthorized access, duplicates, concurrency, rollback, timezone/DST, external failures, destructive actions, and irreversible transitions. Add each unsupported critical behavior as a `test-gap` finding.

- [ ] **Step 3: Assess coverage-tool readiness**

Run:

```powershell
docker compose exec -T app php -m | Select-String 'xdebug|pcov'
docker compose exec -T app php artisan test --coverage-text
```

Expected: if no coverage driver exists, record an operational gap and the exact container change required; do not modify the Dockerfile during the audit.

- [ ] **Step 4: Analyze suite duration and partitioning**

Run major suites separately and record duration:

```powershell
docker compose exec -T app php artisan test tests/Unit --compact
docker compose exec -T app php artisan test tests/Feature/Schema --compact
docker compose exec -T app php artisan test tests/Feature/Panel --compact
docker compose exec -T app php artisan test tests/Feature/Api --compact
docker compose exec -T app php artisan test tests/Feature/Telegram --compact
```

Recommend partitions only when they preserve database isolation and deterministic ordering.

- [ ] **Step 5: Commit test traceability**

```powershell
git add docs/audit/2026-06-14/test-traceability.md docs/audit/2026-06-14/application-inventory.csv docs/audit/2026-06-14/findings.csv docs/audit/2026-06-14/README.md
git commit -m "docs(audit): map tests to application behavior"
```

### Task 11: Audit Build, Deployment, Queue, Logging, Backup, and Recovery

**Files:**
- Create: `docs/audit/2026-06-14/operations.md`
- Modify: `docs/audit/2026-06-14/findings.csv`

- [ ] **Step 1: Review runtime and deployment configuration**

Inspect `docker-compose.yml`, `docker/app/Dockerfile`, `.env.example`, all config files, Composer scripts, npm scripts, storage paths, and cache behavior. Record production-incompatible defaults such as debug mode, default credentials, missing workers, missing scheduler, or absent health checks.

- [ ] **Step 2: Exercise non-destructive operational commands**

Run:

```powershell
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan queue:failed
docker compose exec -T app php artisan schedule:list
```

Expected: record exact results and restore development caches with `optimize:clear`.

- [ ] **Step 3: Verify backup and restore in disposable databases**

Create and restore only disposable databases:

```powershell
docker compose exec -T pgsql pg_dump -U skycenter -Fc skycenter_app_test -f /tmp/skycenter_app_test.dump
docker compose exec -T pgsql dropdb -U skycenter --if-exists skycenter_restore_test
docker compose exec -T pgsql createdb -U skycenter skycenter_restore_test
docker compose exec -T pgsql pg_restore -U skycenter -d skycenter_restore_test --clean --if-exists /tmp/skycenter_app_test.dump
docker compose exec -T pgsql psql -U skycenter -d skycenter_restore_test -c "SELECT count(*) FROM migrations;"
docker compose exec -T pgsql dropdb -U skycenter skycenter_restore_test
```

Expected: restore succeeds and the migrations table is readable. Record backup size, duration, restore duration, and errors.

- [ ] **Step 4: Assess observability and recovery ownership**

Document log channels, retention, sensitive payload risk, failed-job handling, alerting, webhook traceability, health checks, recovery time assumptions, recovery point assumptions, deploy steps, rollback steps, and worker restart requirements.

- [ ] **Step 5: Commit operational evidence**

```powershell
git add docs/audit/2026-06-14/operations.md docs/audit/2026-06-14/findings.csv docs/audit/2026-06-14/README.md
git commit -m "docs(audit): assess operations and recovery"
```

### Task 12: Perform Manual Administrator and Operator Testing

**Files:**
- Create: `docs/audit/2026-06-14/manual-usability.md`
- Modify: `docs/audit/2026-06-14/findings.csv`

- [ ] **Step 1: Prepare disposable users and test records**

Use the test database or a disposable local database. Record user roles and generated record identifiers, but never passwords or tokens. Confirm no production URL or database is active before manual testing.

- [ ] **Step 2: Execute the administrator checklist**

Test login, logout, session expiry, all navigation groups, every list/create/edit page, relation managers, custom actions, uploads, destructive actions, empty states, validation failures, filters, search, sorting, Romanian labels, dates, currency, and Ordinea de Zi.

For each screen record:

```text
Screen | Viewport | Action | Expected | Actual | Result | Evidence | Finding ID
```

Use desktop `1440x900`, tablet `1024x768`, and mobile `390x844` viewports.

- [ ] **Step 3: Execute the operator checklist**

Repeat accessible workflows as operator. Attempt direct URLs for budget, salary, payments, users, and admin-only actions. Record whether access is denied server-side rather than merely hidden.

- [ ] **Step 4: Test usability and recovery behavior**

Assess clarity of errors, destructive confirmations, accidental duplicate submission, back-button behavior, unsaved changes, recoverability, terminology consistency, and practical staff workflow length.

- [ ] **Step 5: Commit manual results**

```powershell
git add docs/audit/2026-06-14/manual-usability.md docs/audit/2026-06-14/findings.csv docs/audit/2026-06-14/README.md
git commit -m "docs(audit): record admin and operator usability results"
```

### Task 13: Assess Engineering Quality and CI Gates

**Files:**
- Create: `docs/audit/2026-06-14/ci-quality-gates.md`
- Modify: `docs/audit/2026-06-14/findings.csv`

- [ ] **Step 1: Run quality and dependency checks**

Run and record:

```powershell
docker compose exec -T app ./vendor/bin/pint --test
docker compose exec -T app composer validate --strict
docker compose exec -T app composer audit --locked
docker compose exec -T app composer outdated --direct
npm audit
npm outdated
```

- [ ] **Step 2: Inspect maintainability risks**

Run:

```powershell
Get-ChildItem app -Recurse -Filter *.php | ForEach-Object { [pscustomobject]@{ Lines=(Get-Content $_.FullName).Count; Path=$_.FullName } } | Sort-Object Lines -Descending | Select-Object -First 30
rg -n "DB::|::query\(|->get\(\)|->all\(\)" app\Filament app\Actions app\Http
rg -n "FIXME|HACK|dd\(|dump\(|ray\(" app routes config tests
```

Review large files, duplicated state machines, hidden coupling, N+1 candidates, and unbounded queries. Do not label size alone as a defect.

- [ ] **Step 3: Define proposed CI jobs**

In `ci-quality-gates.md`, specify exact jobs and commands for:

- Composer validation and audit.
- npm clean install, audit, and production build.
- PostgreSQL-backed PHPUnit suite.
- Pint check.
- Static analysis proposed as a separate remediation because no analyzer is currently configured.
- Optional coverage reporting after a driver is added.

Specify that CI must fail on non-zero exit codes and retain test/build logs.

- [ ] **Step 4: Commit quality assessment**

```powershell
git add docs/audit/2026-06-14/ci-quality-gates.md docs/audit/2026-06-14/findings.csv
git commit -m "docs(audit): define engineering quality gates"
```

### Task 14: Separate Missing Workflows from Defects

**Files:**
- Create: `docs/audit/2026-06-14/missing-workflows.md`
- Modify: `docs/audit/2026-06-14/findings.csv`

- [ ] **Step 1: Compare implementation to all approved specifications**

Read every file under `docs/superpowers/specs/` and relevant files under `docs/plans/`. For each promised capability, classify it as `implemented-and-evidenced`, `implemented-not-evidenced`, `partial`, `absent`, or `superseded`.

- [ ] **Step 2: Interview the repository, not assumptions**

Use routes, resources, actions, tests, and integration docs to determine whether a capability has a complete operational path. CRUD storage without the expected trigger, processing, outcome, or recovery is classified as `partial`, not complete.

- [ ] **Step 3: Write the missing-workflow register**

For each entry record:

```text
Capability | Business need | Existing pieces | Missing pieces | Workaround | Risk | Recommended next design | Evidence
```

Do not propose implementation details beyond enough information to scope a separate brainstorming/design cycle.

- [ ] **Step 4: Commit the workflow-gap assessment**

```powershell
git add docs/audit/2026-06-14/missing-workflows.md docs/audit/2026-06-14/findings.csv
git commit -m "docs(audit): identify missing business workflows"
```

### Task 15: Produce Documentation and Operations Plan

**Files:**
- Create: `docs/audit/2026-06-14/documentation-plan.md`
- Modify: `docs/audit/2026-06-14/findings.csv`

- [ ] **Step 1: Assess existing documentation**

Review `README.md`, `.env.example`, `docs/telegram-bot-setup.md`, specifications, implementation plans, Docker files, and Composer/npm scripts. Record stale, contradictory, missing, or unsafe instructions.

- [ ] **Step 2: Define the replacement documentation set**

Specify exact future documents and ownership:

- `README.md` for system purpose, prerequisites, setup, and common commands.
- `docs/architecture.md` for domains, data flow, and external boundaries.
- `docs/deployment.md` for production deployment and rollback.
- `docs/operations.md` for workers, scheduler, monitoring, and diagnostics.
- `docs/backup-restore.md` for backup, restore, retention, and drills.
- `docs/integrations.md` for n8n, Telegram, email, and outbound messaging contracts.
- `docs/security.md` for roles, secret rotation, incident response, and access review.
- `docs/user-guide.md` for administrator and operator workflows.

- [ ] **Step 3: Define acceptance checks for each document**

Each future document must use commands verified in the target environment, avoid real secrets, identify owners, include failure recovery, and link to relevant application paths.

- [ ] **Step 4: Commit the documentation assessment**

```powershell
git add docs/audit/2026-06-14/documentation-plan.md docs/audit/2026-06-14/findings.csv
git commit -m "docs(audit): plan application documentation"
```

### Task 16: Synthesize Findings and Remediation Backlog

**Files:**
- Create: `docs/audit/2026-06-14/findings.md`
- Create: `docs/audit/2026-06-14/remediation-backlog.md`
- Modify: `docs/audit/2026-06-14/findings.csv`

- [ ] **Step 1: Normalize and validate findings**

Run:

```powershell
$rows = Import-Csv docs\audit\2026-06-14\findings.csv
$duplicateIds = $rows | Group-Object id | Where-Object Count -gt 1
$invalidSeverity = $rows | Where-Object severity -notin @('Critical','High','Medium','Low')
$invalidClass = $rows | Where-Object class -notin @('confirmed-defect','security-risk','operational-gap','missing-workflow','test-gap','maintainability','documentation-gap')
"duplicates=$($duplicateIds.Count) invalid_severity=$($invalidSeverity.Count) invalid_class=$($invalidClass.Count)"
```

Expected: all counts are zero.

- [ ] **Step 2: Challenge every Critical and High finding**

For each Critical or High entry, verify reproduction or direct code proof, affected data/process, realistic impact, and recommended verification. Downgrade unsupported claims rather than inflating risk.

- [ ] **Step 3: Write `findings.md`**

Order by Critical, High, Medium, Low. Each finding contains ID, class, domain, evidence, reproduction, impact, root-cause status, recommendation, and verification criteria.

- [ ] **Step 4: Write `remediation-backlog.md`**

Group work into:

1. Immediate containment.
2. Critical defect/security fixes.
3. High-risk data and integration fixes.
4. Test and observability improvements.
5. Build, deployment, backup, and CI improvements.
6. Medium and Low maintenance work.
7. Separate designs for missing workflows.

Every behavioral remediation states that execution must use systematic debugging and TDD: reproduce, establish root cause, write a failing test, implement the minimal fix, run focused and relevant suites, and request review.

- [ ] **Step 5: Commit synthesis artifacts**

```powershell
git add docs/audit/2026-06-14/findings.csv docs/audit/2026-06-14/findings.md docs/audit/2026-06-14/remediation-backlog.md
git commit -m "docs(audit): synthesize findings and remediation backlog"
```

### Task 17: Create the Final Audit Report and Release Recommendation

**Files:**
- Create: `docs/audit/2026-06-14/final-report.md`
- Modify: `docs/audit/2026-06-14/README.md`

- [ ] **Step 1: Re-run final non-destructive verification**

Run:

```powershell
docker compose exec -T app php artisan test --compact
docker compose exec -T app ./vendor/bin/pint --test
docker compose exec -T app composer validate --strict
docker compose exec -T app composer audit --locked
npm ci
npm run build
git status --short
```

Record current results without fixing failures. Audit documentation changes must not change application test behavior.

- [ ] **Step 2: Write the executive assessment**

`final-report.md` must contain:

- Overall recommendation: `not-ready`, `conditionally-ready`, or `ready`.
- Scope and commit audited.
- Evidence summary.
- Critical and High findings.
- Business workflow confidence by domain.
- Security and authorization confidence.
- Data integrity and recovery confidence.
- Test and build confidence.
- Missing workflow summary.
- Required actions before production use.
- Accepted residual risks.
- Links to every audit artifact.

- [ ] **Step 3: Apply the release decision rule**

Use:

- `not-ready` when any uncontained Critical finding exists, recovery is unverified, authorization bypass is confirmed, or core financial/reservation integrity is unreliable.
- `conditionally-ready` when no uncontained Critical finding exists but High findings require controlled workarounds or monitoring.
- `ready` only when no open Critical or High finding remains and build, tests, authorization, backup/restore, and critical smoke workflows are verified.

- [ ] **Step 4: Complete the audit index**

Change every completed row in `README.md` to `Complete`, add the final recommendation, audited commit SHA, test result, and totals by finding severity/class.

- [ ] **Step 5: Request final code review**

Use the requesting-code-review workflow with:

```text
DESCRIPTION: Full SkyCenter application audit evidence and risk-ranked findings
PLAN_OR_REQUIREMENTS: docs/superpowers/specs/2026-06-14-skycenter-full-application-audit-design.md and this plan
BASE_SHA: b5fda4c
HEAD_SHA: current audit branch HEAD
```

The reviewer checks evidence quality, severity calibration, missing domains, contradictions, unsupported claims, and whether remediation criteria are testable. Fix Critical and Important review findings before completion.

- [ ] **Step 6: Commit the final report**

```powershell
git add docs/audit/2026-06-14/final-report.md docs/audit/2026-06-14/README.md
git commit -m "docs(audit): publish final application assessment"
```

### Task 18: Verify Plan Completion

**Files:**
- Verify: `docs/audit/2026-06-14/`

- [ ] **Step 1: Verify required artifacts exist**

```powershell
$required = @(
  'README.md','baseline.md','application-inventory.md','application-inventory.csv',
  'security-asvs.md','data-integrity.md','workflows-parking-lodging.md',
  'workflows-rental-finance.md','workflows-scheduling-messaging-marketing.md',
  'interconnections.md','test-traceability.md','operations.md','manual-usability.md',
  'findings.csv','findings.md','missing-workflows.md','remediation-backlog.md',
  'final-report.md','ci-quality-gates.md','documentation-plan.md'
)
$missing = $required | Where-Object { -not (Test-Path (Join-Path 'docs\audit\2026-06-14' $_)) }
"missing=$($missing.Count)"
$missing
```

Expected: `missing=0`.

- [ ] **Step 2: Verify document integrity**

```powershell
rg -n "T[B]D|T[O]DO|fill i[n]|implement l[a]ter" docs\audit\2026-06-14
git diff --check b5fda4c..HEAD
git status --short
```

Expected: no unresolved placeholders, no whitespace errors, and a clean worktree.

- [ ] **Step 3: Verify finding integrity**

```powershell
$rows = Import-Csv docs\audit\2026-06-14\findings.csv
$rows | Group-Object severity | Sort-Object Name | Format-Table Name,Count
$rows | Group-Object class | Sort-Object Name | Format-Table Name,Count
$rows | Where-Object { -not $_.evidence -or -not $_.impact -or -not $_.verification } | Format-Table id,title
```

Expected: every finding has evidence, impact, and verification criteria.

- [ ] **Step 4: Report completion**

Report the final recommendation, audited SHA range, test/build results, finding totals, open Critical/High IDs, and links to `final-report.md`, `findings.md`, and `remediation-backlog.md`.

## Execution Boundaries

- Tasks 1-18 produce audit evidence only.
- Mechanical formatting, CI implementation, Docker changes, documentation replacement, and application fixes are separate follow-up work.
- Each confirmed behavioral defect receives its own systematic-debugging and TDD task before production code changes.
- Each missing workflow receives a separate brainstorming/design/specification cycle before implementation.
- Critical and High findings are reviewed before lower-priority remediation begins.
