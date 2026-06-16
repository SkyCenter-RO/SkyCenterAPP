# Security and Authorization Control Audit (OWASP ASVS 5.0)

This document contains the security and authorization assessment for SkyCenter, conducted on June 16, 2026, on branch `audit/full-application` in the worktree `D:\Automation\SkyPark\App\.worktrees\full-application-audit`.

## Authentication and Role Enforcement

SkyCenter uses Laravel 12's native authentication system configured through the `filament` admin panel:
- **Session Auth**: Managed via cookies and session middleware (`StartSession`, `AuthenticateSession`, `VerifyCsrfToken`).
- **User Roles**: The `User` model defines two roles: `admin` and `operator`. Panel access is restricted to active users with either role.
- **Automation Authentication**: API endpoints under `api/automation/*` are secured by the custom `AuthenticateAutomationToken` middleware, which validates a Bearer token against `config('skycenter.automation_api_token')` using `hash_equals`.

---

## Authorization Matrix

This matrix groups the 84 non-vendor routes into logical entry points, along with custom Filament pages and actions.

| Entry point / Path Group | Anonymous | Operator | Admin | Enforcement Location | Automated Evidence | Result |
| :--- | :---: | :---: | :---: | :--- | :--- | :---: |
| **Public web root** (`/`) | Allowed | Allowed | Allowed | `routes/web.php` | None | **pass** |
| **Login page** (`/admin/login`) | Allowed | Allowed | Allowed | `AdminPanelProvider` | `PanelAccessTest::test_login_screen_is_reachable` | **pass** |
| **Panel home / Dashboard** (`/admin`, `/admin/dashboard`) | Redirect | Allowed | Allowed | `AdminPanelProvider` / `canAccessPanel` | `PanelAccessTest::test_active_operator_can_open_panel_home` | **pass** |
| **Ordinea de Zi page** (`/admin/ordinea-de-zi`) | 403 | Allowed | Allowed | `OrdineaDeZi::canAccess()` | `RoleAccessTest::test_operator_can_access_operational_resources` | **pass** |
| **Budget Categories** (`/admin/buget-categorii/*`) | 403 | 403 | Allowed | `BudgetCategoryResource::canAccess()` | `RoleAccessTest::test_operator_is_forbidden_on_admin_only_resources` | **pass** |
| **Budget Transactions** (`/admin/buget-tranzactii/*`) | 403 | 403 | Allowed | `BudgetTransactionResource::canAccess()` | `RoleAccessTest::test_operator_is_forbidden_on_admin_only_resources` | **pass** |
| **Budget Raw Messages** (`/admin/buget-mesaje/*`) | 403 | 403 | Allowed | `BudgetRawMessageResource::canAccess()` | `RoleAccessTest::test_operator_is_forbidden_on_admin_only_resources` | **pass** |
| **Salary Management** (`/admin/buget-salarii/*`) | 403 | 403 | Allowed | `SalaryResource::canAccess()` | `RoleAccessTest::test_operator_is_forbidden_on_admin_only_resources` | **pass** |
| **Payment Resource** (`/admin/sistem-plati/*`) | 403 | 403 | Allowed | `PaymentResource::canAccess()` | `RoleAccessTest::test_operator_is_forbidden_on_admin_only_resources` | **pass** |
| **User Management** (`/admin/sistem-utilizatori/*`) | 403 | 403 | Allowed | `UserResource::canAccess()` | `RoleAccessTest::test_operator_is_forbidden_on_admin_only_resources` | **pass** |
| **Marketing Campaigns** (`/admin/marketing-campanii/*`) | 403 | 403 | Allowed | `MarketingCampaignResource::canAccess()` | `MarketingResourcesAccessTest::test_operator_cannot_access_any_marketing_resource` | **pass** |
| **Marketing Channels** (`/admin/marketing-canale/*`) | 403 | 403 | Allowed | `MarketingChannelResource::canAccess()` | `MarketingResourcesAccessTest::test_operator_cannot_access_any_marketing_resource` | **pass** |
| **Marketing Content Calendar** (`/admin/marketing-calendar/*`) | 403 | 403 | Allowed | `MarketingContentCalendarResource::canAccess()` | `MarketingResourcesAccessTest::test_operator_cannot_access_any_marketing_resource` | **pass** |
| **Marketing Reviews** (`/admin/marketing-recenzii/*`) | 403 | 403 | Allowed | `MarketingReviewResource::canAccess()` | `MarketingResourcesAccessTest::test_operator_cannot_access_any_marketing_resource` | **pass** |
| **Marketing Ad Spend Logs** (`/admin/marketing-cheltuieli/*`) | 403 | 403 | Allowed | `MarketingAdSpendLogResource::canAccess()` | `MarketingResourcesAccessTest::test_operator_cannot_access_any_marketing_resource` | **pass** |
| **Automation Webhook Logs** (`/admin/sistem-automatizari/*`) | 403 | **Allowed** | Allowed | None | None | **fail** (SC-AUD-005) |
| **Message Templates** (`/admin/sistem-sabloane/*`) | 403 | **Allowed** | Allowed | None | None | **fail** (SC-AUD-005) |
| **Outbound Messages** (`/admin/sistem-mesaje-trimise/*`) | 403 | **Allowed** | Allowed | None | None | **fail** (SC-AUD-005) |
| **Parking Lots** (`/admin/parcare-loturi/*`) | 403 | **Allowed** | Allowed | None | None | **fail** (SC-AUD-005) |
| **Parking Prices** (`/admin/parcare-preturi/*`) | 403 | **Allowed** | Allowed | None | None | **fail** (SC-AUD-005) |
| **Parking Customers** (`/admin/parcare-clienti/*`) | 403 | Allowed | Allowed | None | None | **pass** |
| **Parking Reservations** (`/admin/parcare-rezervari/*`) | 403 | Allowed | Allowed | None | `RoleAccessTest::test_operator_can_access_operational_resources` | **pass** |
| **Lodging Properties** (`/admin/cazare-proprietati/*`) | 403 | **Allowed** | Allowed | None | None | **fail** (SC-AUD-005) |
| **Rooms** (`/admin/cazare-camere/*`) | 403 | **Allowed** | Allowed | None | None | **fail** (SC-AUD-005) |
| **Lodging Reservations** (`/admin/cazare-rezervari/*`) | 403 | Allowed | Allowed | None | `RoleAccessTest::test_operator_can_access_operational_resources` | **pass** |
| **Rent Clients** (`/admin/rent-clienti/*`) | 403 | Allowed | Allowed | None | None | **pass** |
| **Rent Vehicles** (`/admin/rent-masini/*`) | 403 | Allowed | Allowed | None | `RoleAccessTest::test_operator_can_access_operational_resources` | **pass** |
| **Rent Contracts** (`/admin/rent-contracte/*`) | 403 | Allowed | Allowed | None | None | **pass** |
| **Rent Maintenance** (`/admin/rent-mentenanta/*`) | 403 | Allowed | Allowed | None | None | **pass** |
| **Automation API** (`api/automation/*`) | 401 | 401 | 401 | `AuthenticateAutomationToken` | `AutomationOutboundMessagesTest::test_missing_token_is_rejected` | **pass** (with token) |

*Note: The **fail** results marked with `SC-AUD-005` denote routes that should be restricted to administrators or made read-only for operators, but are currently fully accessible (with create, edit, and delete privileges) to operators.*

---

## ASVS Control Groups

Assessment of SkyCenter against OWASP ASVS 5.0 control groups:

### V1: Architecture, Design and Security Feedback
- **Status**: **Partial**
- **Details**: Security boundaries are partially defined via panel-level role checks. However, the lack of model-level policies (`Policies/`) and database transaction audit trails allows unchecked modification/deletion of records.

### V2: Authentication Verification Requirements
- **Status**: **Partial**
- **Details**: Uses standard Laravel authentication. Account lockout, brute force protection, password strength enforcement, and Multi-Factor Authentication (MFA) are not implemented or configured.

### V3: Session Management Verification Requirements
- **Status**: **Pass**
- **Details**: Standard Laravel session management with database driver storage. Session lifecycle and expiry times are configured.

### V4: Access Control Verification Requirements
- **Status**: **Fail**
- **Details**: Critical access control failure. The codebase contains no Eloquent Policy classes (`app/Policies/`). Operator users can access reference configuration pages, webhook logs, and sent message logs. Furthermore, operators are permitted to perform destructive actions (edit, delete) on any accessible resource (reservations, clients, contracts) without restriction. See `SC-AUD-004` and `SC-AUD-005`.

### V5: Validation, Sanitization and Active-Content
- **Status**: **Pass**
- **Details**: Filament schema definitions enforce input types, length restrictions, and required fields. Eloquent casts values to proper types.

### V7: Stored Cryptography Verification Requirements
- **Status**: **Pass**
- **Details**: Passwords are encrypted using bcrypt (configured in `config/auth.php` and `BCRYPT_ROUNDS=12` in `.env`).

### V8: Error Handling and Logging Verification Requirements
- **Status**: **Partial**
- **Details**: Webhook logging exists under `AutomationWebhookLog`. However, because this log table is exposed to the Filament panel without read-only restrictions, operators can edit or delete log rows, compromising the audit trail. See `SC-AUD-005`.

### V9: Data Protection Verification Requirements
- **Status**: **Partial**
- **Details**: Customer personal data (name, phone, license plate) is visible to operators. There are no data minimization or access limits for sensitive client records.

### V10: Communication Verification Requirements
- **Status**: **Pass**
- **Details**: The application is configured to run behind standard web servers enforcing HTTPS/TLS.

### V11: Business Logic Verification Requirements
- **Status**: **Partial**
- **Details**: Workflow checks (such as lodging room conflicts and rental booking overlaps) are performed in application logic but lack concurrency locks or database level constraints.

### V12: Secure Coding Verification Requirements
- **Status**: **Pass**
- **Details**: The application uses the Laravel 12 framework, which protects against common web vulnerabilities (SQL injection via Eloquent parameter binding, CSRF protection via middleware, and XSS via Blade rendering).

### V13: API and Web Services Verification Requirements
- **Status**: **Partial**
- **Details**: Automation tokens are safely checked via `hash_equals`. However, no rate-limiting (`throttle`) is applied to any public or automation API routes. See `SC-AUD-006`.

### V14: Configuration Verification Requirements
- **Status**: **Partial**
- **Details**: Configuration parameters are loaded from `.env`. However, the Operator seeder uses hardcoded passwords (`'parola-operator'`) which will run in any environment (including production) without checks. See `SC-AUD-007`.

### V15: File Upload Verification Requirements
- **Status**: **Pass**
- **Details**: Schedule PDF uploads are restricted to `application/pdf` in `FileUpload`, saved to the `local` disk (temp directory), parsed, and immediately deleted in a `finally` block.

---

## Confirmed Security Findings

### SC-AUD-004: Missing Panel Authorization Policies (Eloquent Policies)
- **Severity**: High
- **Class**: `security-risk`
- **Domain**: Identity and access
- **Title**: Operator users can perform destructive operations (edit/delete) on critical resources
- **Evidence**: `app/Providers/AppServiceProvider.php` (no policy registration); absence of `app/Policies` directory.
- **Reproduction**: Authenticate as an operator user, navigate to `/admin/parcare-rezervari`, select a reservation, and click "Delete". The record will be deleted successfully.
- **Impact**: Operational integrity risk. Operators can accidentally or maliciously delete customers, lodging bookings, or rental contracts, leading to loss of financial data or booking history.
- **Recommendation**: Create Eloquent Policy classes for all models and register them. Restrict write/delete actions on critical resources (reservations, vehicles, clients) to administrators, or require second-party approval.

### SC-AUD-005: Unauthorized Access to System Configuration and Logs
- **Severity**: High
- **Class**: `security-risk`
- **Domain**: Identity and access
- **Title**: Operator users can view and mutate system configuration, templates, and webhook logs
- **Evidence**: `app/Filament/Resources/AutomationWebhookLogs/AutomationWebhookLogResource.php` and other resources in the "Sistem" navigation group do not implement `canAccess()`.
- **Reproduction**: Authenticate as an operator user, navigate directly to `/admin/sistem-automatizari`, `/admin/sistem-sabloane`, or `/admin/sistem-mesaje-trimise`. Full access is allowed, including editing and deleting templates or logs.
- **Impact**: System integrity and audit trail tampering risk. Operators can delete automation event logs, modify system message templates, or view system-wide webhook details.
- **Recommendation**: Add resource-level `canAccess()` overrides to system management and configuration resources, restricting them to admins or making them read-only for operators.

### SC-AUD-006: Missing API Rate Limiting
- **Severity**: Medium
- **Class**: `security-risk`
- **Domain**: Messaging and automation
- **Title**: No rate limiting configured on automation and webhook API endpoints
- **Evidence**: `routes/api.php:10` and `bootstrap/app.php:14-18` (lack of throttle middleware on the automation group).
- **Reproduction**: Send rapid requests to `api/automation/parking-reservations`. The application processes each request without rate restrictions.
- **Impact**: Denial of Service (DoS) and brute force vulnerability on automation tokens.
- **Recommendation**: Apply Laravel's built-in throttle middleware (e.g. `middleware('throttle:60,1')`) to the api routes group.

### SC-AUD-007: Insecure Hardcoded Operator Passwords in Seeders
- **Severity**: Medium
- **Class**: `security-risk`
- **Domain**: Identity and access
- **Title**: Operator user seeder creates accounts with hardcoded passwords in any environment
- **Evidence**: `database/seeders/OperatorUserSeeder.php:26` (`Hash::make('parola-operator')`).
- **Reproduction**: Run `php artisan db:seed --class=OperatorUserSeeder` in production environment. Accounts will be created with default passwords.
- **Impact**: Weak initial authentication. Default accounts with predictable credentials may remain active in production if not explicitly modified.
- **Recommendation**: Restrict the seeder to local/development environments, or fetch credentials from env variables (like `ADMIN_BOOTSTRAP_PASSWORD` for the admin).
