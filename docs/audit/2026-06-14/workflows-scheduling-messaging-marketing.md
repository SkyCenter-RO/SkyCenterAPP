# Scheduling, Messaging, Automation, and Marketing Workflow Audit

This document contains the workflow trace and gap analysis for the Scheduling, Messaging, Automation, and Marketing domains, conducted on June 16, 2026, on branch `audit/full-application` in the worktree `D:\Automation\SkyPark\App\.worktrees\full-application-audit`.

---

## 1. Scheduling Workflow Trace (PDF Import & Ordinea de Zi)

### Workflow Definition

| Step | Detail |
| :--- | :--- |
| **Trigger** | Admin clicks "Încarcă Program Ture" header action on the Ordinea de Zi page (`admin/ordinea-de-zi`) and uploads a PDF. |
| **Validation** | Form: accepted type `application/pdf`, required. No size validation limits. |
| **Persistence** | Parses PDF using `Smalot\PdfParser\Parser`. Upserts `WorkShift` records via `updateOrCreate` for day/night shifts based on parsed date. |
| **State Transition** | Inserts/updates work shifts for the month. |
| **Audit Record** | None. |
| **Message Side Effect** | None. |
| **Retry Behavior** | Idempotent on shift level (re-upload overwrites existing shifts on the same date/type). |
| **Existing Test** | `tests/Feature/Scheduling/OrdineaDeZiDisplayTest.php`, `tests/Feature/Scheduling/OrdineaDeZiUploadTest.php`, `tests/Feature/Scheduling/ParseSchedulePdfActionTest.php`. |
| **Gaps & Issues** | Name spelling mismatch: if an operator name in the PDF does not match the database name case-insensitively, the shift is saved with a null `user_id` (raw employee name stored), meaning the shift will not link to the operator's account. No maximum file size limit on upload. |

---

## 2. Messaging and Automation Lifecycle Trace

### Workflow Definition

| Step | Detail |
| :--- | :--- |
| **Trigger** | Reservation status change to `'booked'` (confirmation) or webhook cron triggers `api/automation/dispatch-review-requests` (review request). |
| **Validation** | Verifies active template exists and customer has correct contact details (phone or email). |
| **Persistence** | Creates a pending `OutboundMessage` record with target channel, template key, and payload text. Logs `AutomationEvent`. |
| **State Transition** | Outbound messages transition: `pending` -> `sent` / `failed` (updated via callback). |
| **Audit Record** | `AutomationEvent` and `AutomationWebhookLog` record the queue and callback outcomes. |
| **Message Side Effect** | External messaging provider sends the message and hits callback URL `POST api/automation/outbound-messages/{id}/callback`. |
| **Retry Behavior** | Webhook callback controller is idempotent: duplicate callbacks on already processed messages are a no-op. |
| **Existing Test** | `tests/Feature/Messaging/ConfirmationMessageTest.php`, `tests/Feature/Actions/Messaging/RenderMessageTemplateTest.php`, `tests/Feature/Api/AutomationOutboundMessagesTest.php`. |
| **Gaps & Issues** | Missing template placeholders are left raw in the message text. Skipped messages (due to missing contact info or templates) are logged as skipped events silently without notifying administrators. |

---

## 3. Marketing Domain Classification

| Resource | Classification | Workflow Completeness | Gaps |
| :--- | :--- | :--- | :--- |
| **MarketingCampaigns** | `crud-only` | Storage-only resource. | No automated publication or sync. |
| **MarketingChannels** | `crud-only` | Storage-only reference list. | No direct utility outside references. |
| **MarketingReviews** | `crud-only` | Storage-only. | No automated external review collection. |
| **MarketingCalendar** | `crud-only` | Storage-only scheduler. | No integration with third-party calendar providers. |
| **MarketingAdSpend** | `crud-only` | Storage-only logs. | No automation to fetch ad costs from platforms. |

---

## 4. Confirmed Scheduling, Messaging & Marketing Findings

### SC-AUD-016: Missing PDF File Size and Upload Constraints
- **Severity**: Low
- **Class**: `security-risk`
- **Domain**: Scheduling
- **Title**: Upload action on Ordinea de Zi page lacks file size limits
- **Evidence**: `app/Filament/Pages/OrdineaDeZi.php:219-224` (form upload has no `maxSize` constraint).
- **Reproduction**: Upload a massive PDF file (e.g. 50MB) to the schedule import. The server attempts to store and parse it, leading to resource exhaustion (out-of-memory or timeout errors).
- **Impact**: Server denial-of-service (DoS) via memory exhaustion.
- **Recommendation**: Add a size limit constraint to the FileUpload component (e.g., `->maxSize(5120)` to limit files to 5MB).

### SC-AUD-017: Unresolved Template Placeholder Substitution Gaps
- **Severity**: Low
- **Class**: `maintainability`
- **Domain**: Messaging and automation
- **Title**: Unresolved placeholders in message templates are sent raw to customers
- **Evidence**: `app/Actions/Messaging/RenderMessageTemplate.php:26` (`strtr($template->body, $placeholders)`).
- **Reproduction**: Render a template containing `{{name}}` but pass `['guest_name' => 'Maria']` in placeholders. The output message contains the literal string `"{{name}}"`.
- **Impact**: Unprofessional communication sent to customers due to template variable mismatch.
- **Recommendation**: Validate that all placeholders in the template body are resolved, or strip unresolved double-bracketed variables before queueing.

### SC-AUD-018: Marketing Domain is Purely Storage CRUD
- **Severity**: Low
- **Class**: `documentation-gap`
- **Domain**: Marketing
- **Title**: The Marketing domain contains no active business workflows (CRUD only)
- **Evidence**: Absence of observers, custom event listeners, or actions under `app/Actions` for the marketing resources.
- **Reproduction**: Review the marketing resources; they perform standard storage updates with no side effects or external API links.
- **Impact**: Operational expectations mismatch. Users might expect automated campaign dispatch or analytics sync, which do not exist.
- **Recommendation**: Document clearly that the marketing domain is a storage-only tracker, or plan integration designs for future stages.
