# Application and Integration Inventory

## Evidence Commands

Task 3 inventory is based on these commands run from `D:\Automation\SkyPark\App\.worktrees\full-application-audit` on branch `audit/full-application`:

```powershell
rg --files app routes config database tests resources docker | Sort-Object
docker run --rm --network app_default -e APP_KEY="<ephemeral-test-key>" -v "D:\Automation\SkyPark\App\.worktrees\full-application-audit:/var/www/html" -w /var/www/html app-app php artisan route:list --except-vendor --json
rg -n "env\(|config\(" app config routes
rg -n "Http::|Mail::|Notification::|dispatch\(|Queue::|Storage::|Telegram|n8n|webhook|callback" app routes config docs
rg -n "observe\(|Observer|booted\(|creating\(|created\(|updating\(|updated\(" app
```

The generated CSV is `docs/audit/2026-06-14/application-inventory.csv` and uses the required header:

```csv
component_type,domain,path_or_route,responsibility,entry_point,dependencies,side_effects,authorization,test_evidence,status
```

## Component Counts

| Component type | Count |
|---|---:|
| action | 8 |
| controller | 8 |
| custom_page | 1 |
| external_boundary | 9 |
| filament_resource | 25 |
| middleware | 1 |
| migration_group | 19 |
| model | 35 |
| observer | 2 |
| relation_manager | 7 |
| route | 84 |
| seeder | 8 |

## Domain Component Counts

| Domain | Count |
|---|---:|
| Budget and Telegram | 31 |
| Identity and access | 8 |
| Lodging | 25 |
| Marketing | 31 |
| Messaging and automation | 32 |
| Parking | 33 |
| Payments | 8 |
| Scheduling | 6 |
| System | 10 |
| Vehicle rental | 23 |

## Request Paths

| Surface | Request paths | Evidence |
|---|---|---|
| Public web | `GET|HEAD /` | `routes/web.php:5`; route inventory row `GET|HEAD /` |
| Web panel | `admin/*`, including Filament login/dashboard/resource routes | `app/Providers/Filament/AdminPanelProvider.php:29` sets panel path `admin`; route inventory has 76 `admin/*` rows |
| Automation API | `POST api/automation/parking-reservations`, `POST api/automation/lodging-reservations`, `POST api/automation/dispatch-review-requests`, `GET api/automation/outbound-messages`, `POST api/automation/outbound-messages/{outboundMessage}/callback` | `routes/api.php:10-15` |
| Telegram | `POST api/automation/telegram/income`, `POST api/automation/telegram/expense` | `routes/api.php:16-17`; `docs/telegram-bot-setup.md:28-71` describes n8n/Telegram workflow ownership |
| Uploads | Filament `admin/ordinea-de-zi` header action uploads `schedule_pdf` to `local` disk directory `temp-schedules` | `app/Filament/Pages/OrdineaDeZi.php:220-245` |
| Outbound callbacks | `POST api/automation/outbound-messages/{outboundMessage}/callback` | `routes/api.php:15`; `app/Http/Controllers/Api/Automation/OutboundMessageCallbackController.php:16-52` |

Route inventory breakdown: 1 public root route, 76 Filament panel routes, and 7 automation API routes.

## External Boundaries and Configuration Variables

| Boundary | Config variables and ownership | Evidence |
|---|---|---|
| PostgreSQL database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE` | `.env.example:25-30`; `config/database.php:89-99` |
| n8n automation API caller | `AUTOMATION_API_TOKEN`, `AUTOMATION_DEFAULT_PARKING_LOT_ID` | `.env.example:69-70`; `config/skycenter.php:5-6`; `routes/api.php:10-17` |
| Telegram bots/groups | Bot tokens live outside the repo; app receives normalized payloads through n8n with `AUTOMATION_API_TOKEN` | `docs/telegram-bot-setup.md:1-71`; `routes/api.php:16-17` |
| Outbound messaging provider/n8n | `AUTOMATION_API_TOKEN`; provider credentials are outside the repo | `routes/api.php:14-15`; `app/Http/Controllers/Api/Automation/OutboundMessagesController.php:12-24`; `app/Http/Controllers/Api/Automation/OutboundMessageCallbackController.php:16-52` |
| Uploaded PDFs and filesystem storage | `FILESYSTEM_DISK`, `APP_URL`, optional `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` | `.env.example:39,61-65`; `config/filesystems.php:16,44,52-58`; `app/Filament/Pages/OrdineaDeZi.php:220-245` |
| Mail providers | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `POSTMARK_API_KEY`, `RESEND_API_KEY` | `.env.example:52-59`; `config/mail.php:17-49,114-115`; `config/services.php:18-22` |
| Queue backend | `QUEUE_CONNECTION`, `DB_QUEUE_CONNECTION`, `DB_QUEUE_TABLE`, `DB_QUEUE`, `DB_QUEUE_RETRY_AFTER`, `QUEUE_FAILED_DRIVER`, optional `REDIS_QUEUE_*`, `SQS_*` | `.env.example:40`; `config/queue.php:16,40-43,58-71,124-125` |
| Cache and sessions | `CACHE_STORE`, `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_SECURE_COOKIE`, `REDIS_*`, `DB_CACHE_*` | `.env.example:32-43,47-50`; `config/cache.php:18,44-47,77-87`; `config/session.php:21-215` |
| Admin bootstrap secret | `ADMIN_BOOTSTRAP_PASSWORD` | `.env.example:7`; `config/skycenter.php:4`; `database/seeders/AdminUserSeeder.php` inventory row |

No secret values were recorded. The route-list command in this document uses `<ephemeral-test-key>` instead of the runtime APP_KEY value.

## Side Effects

### Queue and Outbound Messaging

The search found queue configuration but no direct application code references to `dispatch(` or `Queue::` under `app`, `routes`, or `config`. Outbound delivery is represented as persisted `outbound_messages` records for external n8n/provider workers rather than Laravel queued jobs in the inspected code.

Evidence:

- `config/queue.php:16` sets the default queue connection from `QUEUE_CONNECTION`.
- `database/migrations/0001_01_01_000002_create_jobs_table.php:14-39` creates `jobs`, `job_batches`, and `failed_jobs`.
- `app/Actions/Messaging/QueueConfirmationMessage.php:103` creates `OutboundMessage` rows and `app/Actions/Messaging/QueueConfirmationMessage.php:113` records an `AutomationEvent`.
- `app/Actions/Automation/DispatchReviewRequests.php:116` creates review-request `OutboundMessage` rows and `app/Actions/Automation/DispatchReviewRequests.php:128` records events.

Inventory status for the queue backend is `consumer-unknown` because the schema/config exist, but Task 3 evidence did not find application dispatch or worker usage.

### Observers

`app/Providers/AppServiceProvider.php:26-27` registers `ParkingReservationObserver` and `LodgingReservationObserver`. The observers call `QueueConfirmationMessage` from `created` and `updated` hooks:

- `app/Observers/ParkingReservationObserver.php:14-24`
- `app/Observers/LodgingReservationObserver.php:14-24`

The side effect is outbound confirmation message creation when reservation status reaches the action's eligible state. Coverage is represented in `tests/Feature/Messaging/ConfirmationMessageTest.php`.

### Persistence Effects

| Path | Persisted effect | Evidence |
|---|---|---|
| Parking webhook | Creates/updates `parking_customers`, `parking_reservations`; records `automation_events` | `app/Http/Controllers/Api/Automation/ParkingReservationWebhookController.php:17`; `app/Actions/Automation/UpsertParkingReservationFromWebhook.php:17-67` |
| Lodging webhook | Creates/updates `lodging_reservations`; records `automation_events` | `app/Http/Controllers/Api/Automation/LodgingReservationWebhookController.php:17`; `app/Actions/Automation/UpsertLodgingReservationFromWebhook.php:16-64` |
| Outbound callback | Updates `outbound_messages`; records `automation_webhook_logs` and `automation_events` | `app/Http/Controllers/Api/Automation/OutboundMessageCallbackController.php:16-52` |
| Telegram income | Creates/updates `telegram_sessions`; creates `budget_transactions` | `app/Actions/Telegram/ProcessIncomeTelegramUpdate.php:13-57,229` |
| Telegram expense | Creates/updates `telegram_sessions`; creates `budget_transactions` | `app/Actions/Telegram/ProcessExpenseTelegramUpdate.php:12-54,119` |
| Schedule PDF upload | Stores temp upload, parses it, upserts `work_shifts`, deletes temp upload | `app/Filament/Pages/OrdineaDeZi.php:220-245`; `app/Actions/Scheduling/ParseSchedulePdfAction.php:67-75` |
| Payment audit data | `payment_change_audits` has model/schema/test evidence, but Task 3 did not find an observer/action that automatically writes audits | `app/Models/Payment.php:40`; `tests/Feature/Panel/PaymentModelsTest.php:29` |
| Parking status audit data | `parking_status_audits` has model/schema/test evidence, but Task 3 did not find an observer/action that automatically writes status audit rows | `app/Models/ParkingReservation.php:61`; `tests/Feature/Panel/ParkingModelsTest.php:39,109` |

## Orphan Candidates and Consumer-Unknown Boundaries

These are not classified as defects in Task 3. They are evidence gaps for later workflow/security/data-integrity tasks.

| Candidate | Status | Evidence |
|---|---|---|
| `ParkingSpace` | `consumer-unknown` | Model exists at `app/Models/ParkingSpace.php:8`; relationships exist in `app/Models/ParkingLot.php:21`, `app/Models/ParkingZone.php:22`, and `app/Models/ParkingReservation.php:51`; no `ParkingSpaceResource` or direct route was found in the source list. Tests exercise the model in `tests/Feature/Panel/ParkingModelsTest.php:51-72`. |
| Mail boundary | `consumer-unknown` | Mail config exists in `config/mail.php:17-49`, but Task 3 integration search did not find `Mail::` usage under `app`, `routes`, or `config`. |
| Laravel queue backend | `consumer-unknown` | Queue config and tables exist, but Task 3 search found no `dispatch(` or `Queue::` use in application code. Outbound delivery appears database-polled by n8n/provider rather than Laravel-worker based. |

## Specification Drift

Two conservative spec-drift rows are included in the CSV and no new finding was added yet:

| Component | Drift | Evidence |
|---|---|---|
| `AutomationWebhookLogResource` | The web-app spec describes `Jurnal automatizari` / `automation_*` as read-only, but the current Filament resource has edit routing and table edit/delete actions while only `canCreate()` is disabled. This should be reviewed in the security/manual tasks before deciding severity. | Spec: `docs/superpowers/specs/2026-06-08-skycenter-web-app-design.md:24,70,76`; resource: `app/Filament/Resources/AutomationWebhookLogs/AutomationWebhookLogResource.php:34-37,56-61`; table actions: `app/Filament/Resources/AutomationWebhookLogs/Tables/AutomationWebhookLogsTable.php:52-58` |
| `OutboundMessageResource` | The web-app spec places `outbound_messages` in a read-only integration context, but the current Filament resource exposes create and edit routes, list-page create action, edit-page delete action, and table edit/delete actions. This should be reviewed in the security/manual tasks before deciding severity. | Spec: `docs/superpowers/specs/2026-06-08-skycenter-web-app-design.md:76`; resource routes: `app/Filament/Resources/OutboundMessages/OutboundMessageResource.php:50-56`; table actions: `app/Filament/Resources/OutboundMessages/Tables/OutboundMessagesTable.php:46-52`; list create action: `app/Filament/Resources/OutboundMessages/Pages/ListOutboundMessages.php:13-17`; edit delete action: `app/Filament/Resources/OutboundMessages/Pages/EditOutboundMessage.php:13-17` |

## Route Count Validation

The Artisan route JSON returned 84 non-vendor routes. `application-inventory.csv` contains 84 rows with `component_type` equal to `route`; route count parity is therefore satisfied.

Validation command required by Task 3:

```powershell
$routes = (docker run --rm --network app_default -e APP_KEY="<ephemeral-test-key>" -v "D:\Automation\SkyPark\App\.worktrees\full-application-audit:/var/www/html" -w /var/www/html app-app php artisan route:list --except-vendor --json | ConvertFrom-Json).Count
$inventoryRoutes = (Import-Csv docs\audit\2026-06-14\application-inventory.csv | Where-Object component_type -eq 'route').Count
"routes=$routes inventory_routes=$inventoryRoutes"
```

Expected and observed result: `routes=84 inventory_routes=84`.

## Findings Register Impact

`docs/audit/2026-06-14/findings.csv` was not changed in Task 3. The inventory documents evidence gaps and two spec-drift candidates, but none were promoted to a new finding without the later authorization/manual workflow validation tasks.
