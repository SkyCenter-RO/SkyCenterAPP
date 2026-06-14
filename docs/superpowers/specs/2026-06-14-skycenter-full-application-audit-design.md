# SkyCenter Full Application Audit Design

## Context

SkyCenter is a Laravel 12 and Filament 5 internal operations application covering parking, lodging, vehicle rental, payments, budgeting, Telegram workflows, scheduling, outbound messaging, automation webhooks, and marketing. It currently contains 215 application PHP files, 19 migrations, and 42 PHP test files.

The audit baseline established on June 14, 2026 is:

- Docker services for the application and PostgreSQL are running.
- The application exposes 84 non-vendor routes.
- The automated test suite passes: 166 tests and 454 assertions in approximately 220 seconds.
- Laravel Pint reports 78 files with style violations.
- The frontend build cannot currently be reproduced because Node dependencies are not installed on the host and the application container does not include Node.js.
- `README.md` is still the default Laravel document and does not explain the SkyCenter system, deployment, integrations, backup, recovery, or operations.

Passing tests are useful evidence, but they do not establish production readiness or workflow completeness. The audit will therefore assess whether the current tests cover the correct behavior and whether the connected business processes remain correct across component boundaries.

## Objective

Produce an evidence-based assessment of whether SkyCenter can safely support real business operations and identify missing, incomplete, insecure, or insufficiently tested behavior.

The audit will not treat code coverage as the objective. Coverage is a diagnostic measure. The objective is confidence in critical business behavior, data integrity, access control, failure recovery, and operability.

## Recommended Strategy

Use a risk-driven audit in two major stages:

1. Establish production safety and operational reliability.
2. Assess workflow completeness, interconnections, and usability.

This order prevents cosmetic or low-value work from obscuring risks involving financial data, reservations, authentication, automation, or recovery.

## Scope

### Application Architecture and Inventory

Create a system map covering:

- Laravel routes, controllers, middleware, actions, observers, models, migrations, seeders, policies, Filament resources, pages, relation managers, and scheduled or queued work.
- External boundaries such as PostgreSQL, n8n, Telegram, email, outbound messaging providers, uploaded PDFs, and filesystem storage.
- Configuration and secret propagation from environment variables into application behavior.
- Domain ownership and dependencies between parking, lodging, rental, payments, budgeting, scheduling, messaging, automation, and marketing.

The inventory must identify orphaned code, routes without clear consumers, data models without user-facing workflows, and specifications that were not implemented or are no longer accurate.

### Production Readiness

Assess:

- Reproducible setup from a clean checkout.
- Dependency installation and deterministic backend/frontend builds.
- Environment validation and separation of development, testing, and production settings.
- Database migration safety, seed behavior, indexes, constraints, transaction boundaries, and rollback limitations.
- Queue workers, retries, failed jobs, idempotency, timeouts, and duplicate delivery.
- Log usefulness, sensitive-data exposure, exception handling, and alerting gaps.
- File upload validation, retention, permissions, and cleanup.
- Backup, restore, disaster recovery, and recovery verification.
- Deployment, maintenance mode, cache optimization, worker restart, and rollback procedures.
- Health checks and operational monitoring.

### Security and Authorization

Use OWASP ASVS 5.0 as the security checklist, scaled to this internal business application. Include:

- Authentication, session handling, password policy, account lifecycle, disabled users, and optional MFA readiness.
- Admin/operator authorization on navigation, pages, resource actions, relation managers, custom actions, API routes, and direct record access.
- Automation-token validation, rotation readiness, comparison behavior, logging, and rate-limiting requirements.
- Input validation, mass assignment, SQL injection, XSS, CSRF, insecure direct object references, unsafe file handling, and validation bypasses.
- Secret handling in `.env`, examples, Docker configuration, logs, tests, and documentation.
- Protection of customer, reservation, financial, salary, and message data.
- Dependency vulnerability checks for Composer and npm packages.
- Security headers, debug mode, error disclosure, and production defaults.

Authorization must be tested at every entry point. Hiding a Filament navigation item is not accepted as authorization evidence.

### Business Workflow Audit

Trace each critical workflow from input to persisted state and subsequent side effects.

#### Parking

- Customer and reservation creation and update.
- Duplicate or repeated webhook delivery.
- Status transitions and audit history.
- Lot, zone, price, capacity, arrival, and departure relationships.
- Confirmation and outbound message generation.
- Invalid dates, missing reference data, conflicting records, and partial failures.

#### Lodging

- Property, room, reservation, and synchronization-link relationships.
- Source-specific reservation ingestion.
- Arrival/departure handling and dashboard display.
- Review-request eligibility, dispatch, callback, and duplicate prevention.
- Confirmation messaging and failure recovery.

#### Vehicle Rental

- Client, vehicle, contract, image, and maintenance relationships.
- Vehicle availability across overlapping contracts and maintenance periods.
- Pickup/return behavior and dashboard display.
- Financial linkage and invalid state transitions.

#### Payments and Budgeting

- Payment creation and change auditing.
- Role restrictions for financial data.
- Income and expense Telegram wizard state transitions.
- Category assignment, raw-message retention, confirmation, cancellation, retry, and duplicate update handling.
- Salary and budget transaction correctness.
- Currency, decimal precision, date boundaries, and negative or zero values.

#### Scheduling

- PDF upload authorization and validation.
- Parsing correctness for expected and malformed documents.
- Re-upload, duplicate shifts, partial parsing, and replacement behavior.
- Correct display by date, employee, and role.

#### Messaging and Automation

- Template rendering and missing-variable behavior.
- Message queuing, claiming, delivery callbacks, retries, terminal failures, and idempotency.
- Webhook logging and event traceability.
- Authentication and validation at every automation endpoint.
- Clear ownership of retries between Laravel, n8n, and external providers.

#### Marketing

- Access control and CRUD integrity for campaigns, channels, reviews, content calendar, and advertising spend.
- Date, status, budget, and channel relationships.
- Identification of features that are storage-only versus complete operational workflows.

### Interconnection Analysis

Build a dependency matrix showing where one domain relies on another. At minimum, inspect:

- Reservations to customers, properties, lots, rooms, prices, and payments.
- Reservation changes to observers, audit records, confirmation messages, and automation events.
- Telegram updates to sessions, categories, raw messages, and budget transactions.
- Scheduling uploads to parsed shifts and the Ordinea de Zi page.
- Review dispatch to lodging reservations and outbound messages.
- User roles to every sensitive resource and API operation.

For each connection, record the initiating event, expected state change, side effects, retry behavior, and evidence from tests or manual verification.

### Automated Test Assessment

Create a traceability matrix from behavior to tests. Classify tests as:

- Unit tests for isolated deterministic logic.
- Feature tests for database-backed application behavior.
- Authorization tests for each role and entry point.
- Contract tests for inbound and outbound integration payloads.
- End-to-end smoke tests for the highest-value operator workflows.
- Operational tests for migrations, queues, builds, backup/restore, and deployment commands.

Add coverage tooling only after the test environment is reproducible. Coverage reports must be interpreted by risk area and uncovered behavior, not by a single percentage target.

The audit must specifically look for missing tests involving:

- Validation failures and malformed payloads.
- Unauthorized direct access.
- Duplicate requests and idempotency.
- Concurrency and overlapping reservations/contracts.
- Transaction rollback after partial failure.
- Time zones, daylight-saving transitions, date boundaries, and locale behavior.
- External service errors and retries.
- Destructive actions and irreversible state changes.

### Manual and Usability Assessment

Exercise the application as both administrator and operator. Review:

- Login, logout, session expiry, and access-denied behavior.
- Navigation completeness and role-appropriate visibility.
- Every list, create, and edit page for required fields, validation, defaults, filters, sorting, search, empty states, and error messages.
- Destructive actions, confirmation prompts, and recoverability.
- Romanian terminology, date/time formatting, currency display, and consistency.
- Desktop and practical mobile/tablet layouts used by staff.
- The Ordinea de Zi workflow as the primary operational dashboard.

Missing workflows must be distinguished from defects. A defect violates intended behavior; a missing workflow has no complete implementation despite a business need.

### Engineering Quality and Maintainability

Assess:

- Formatting and static analysis.
- Dependency health and abandoned packages.
- Dead code, duplicate logic, oversized classes, hidden coupling, and unclear boundaries.
- Consistency between migrations, model casts, validation rules, forms, actions, and tests.
- Query count and obvious N+1 or unbounded-list risks.
- Test duration and opportunities for safe suite partitioning.
- CI enforcement for backend tests, frontend build, formatting, static analysis, and dependency auditing.

Formatting changes are mechanical and must not be mixed with behavioral fixes in the same review unit.

### Documentation and Operations

Replace the template README and create documentation for:

- System purpose and domain overview.
- Local setup and clean-environment verification.
- Environment variables and secret ownership.
- Database migration and seeding.
- Frontend asset build.
- Test commands and test database isolation.
- Queue workers and scheduled tasks.
- n8n, Telegram, email, and outbound messaging integrations.
- Deployment and rollback.
- Backup, restore, and disaster recovery.
- Common failures and diagnostic steps.
- User roles and operational responsibilities.

## Audit Method

### Phase 1: Evidence Collection

- Do not modify production behavior.
- Run existing checks and record exact commands, environment, duration, and results.
- Map components, workflows, tests, configuration, and external dependencies.
- Review recent commits and specifications for implementation drift.

### Phase 2: Risk Analysis

- Rank findings by likelihood, impact, detectability, and affected business process.
- Confirm each suspected defect with a reproducible example or direct code-path evidence.
- Separate confirmed defects, security risks, operational gaps, missing features, maintainability issues, and documentation gaps.

### Phase 3: Remediation Planning

- Group fixes into small, reviewable units.
- Address Critical and High risks before Medium and Low risks.
- Avoid unrelated refactoring.
- Define acceptance criteria and exact verification commands for every remediation.

### Phase 4: Remediation Execution

For every behavioral defect:

1. Reproduce the defect consistently.
2. Trace the data flow and establish root cause.
3. Write the smallest failing regression test.
4. Confirm the test fails for the expected reason.
5. Implement the minimal root-cause fix.
6. Confirm the focused test and full relevant suite pass.
7. Request code review at each major checkpoint.

New features and missing workflows require a separately approved design before implementation.

### Phase 5: Final Verification

- Run the complete automated suite.
- Run formatting, static analysis, dependency audits, and frontend production build.
- Execute critical smoke tests as administrator and operator.
- Verify backup restoration in a disposable environment.
- Confirm all Critical and High findings are closed or explicitly accepted with documented reasoning.

## Finding Severity

### Critical

Immediate risk of unauthorized access, sensitive-data disclosure, unrecoverable data loss, corrupt financial/booking state, or complete operational outage.

### High

Likely business disruption, incorrect reservations/payments, privilege bypass, integration duplication, or inability to recover reliably.

### Medium

Limited workflow failure, weak validation, poor diagnostics, incomplete tests, performance risk, or manual workaround required.

### Low

Maintainability, consistency, minor usability, formatting, or documentation issue with limited immediate business impact.

## Deliverables

The audit will produce:

1. Application and integration map.
2. Route, component, and data-flow inventory.
3. Business workflow and interconnection matrix.
4. Test traceability and coverage-gap report.
5. OWASP ASVS-based security checklist and findings.
6. Production-readiness and disaster-recovery checklist.
7. Manual admin/operator usability report.
8. Missing-feature and incomplete-workflow register.
9. Risk-ranked defect report with reproduction evidence.
10. Prioritized remediation backlog with acceptance criteria.
11. CI and quality-gate proposal.
12. Updated technical and operational documentation plan.

## Acceptance Criteria

The audit is complete when:

- Every application domain and external boundary is represented in the system map.
- Every critical workflow has documented inputs, state changes, side effects, failure modes, and test evidence.
- Every route and sensitive action has an authorization assessment.
- The build and test environment can be reproduced from documented steps, or the blocking gap is documented as a finding.
- Security, data integrity, backup/recovery, deployment, and observability have explicit assessments.
- Findings are reproducible, severity-ranked, and linked to affected components.
- Missing features are separated from defects and receive their own future design work.
- The remediation backlog is ordered by business risk and contains verification criteria.

## Out of Scope During Initial Audit

- Production database mutation or destructive testing.
- Unapproved changes to external n8n, Telegram, email, or provider accounts.
- Building the deferred Android application.
- Large architectural rewrites before evidence demonstrates they are necessary.
- Implementing newly discovered features before they receive a separate approved design.

## Reference Standards

- Laravel 12 official documentation for testing, deployment, queues, configuration, logging, and database behavior.
- Filament 5 official security, authorization, and testing documentation.
- OWASP Application Security Verification Standard 5.0.
- PHPUnit guidance for coverage and strict test execution.

