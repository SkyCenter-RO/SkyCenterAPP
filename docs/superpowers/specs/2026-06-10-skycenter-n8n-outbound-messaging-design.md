# Sky Center — Integrare n8n: Mesagerie outbound (Subproiect #3, Flux #2)

## Context

Acest spec acoperă al doilea flux din subproiectul #3 (Integrări n8n): trimiterea automată de
mesaje către clienți — confirmarea rezervării (parcare/cazare) și cererea de recenzie la
24h după plecare ("departed + 24h"). Continuă pattern-ul "n8n subțire" din flux #1
(parsing email → rezervări, finalizat, merge `b0f6b16`).

Flux #3 (parsing buget Telegram) rămâne un spec separat, ulterior.

## Arhitectură

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Laravel (subproiect #2)                      │
│                                                                        │
│  Operator confirmă o rezervare în panou (Filament)                   │
│  parking_reservations.status: pending_approval -> booked             │
│  lodging_reservations.status: pending -> confirmed                   │
│         │                                                             │
│         ▼                                                             │
│  ParkingReservationObserver / LodgingReservationObserver             │
│         │ randează șablonul (message_templates) cu datele rezervării │
│         ▼                                                             │
│  outbound_messages (status='pending', payload.text=mesaj gata)       │
│         │                                                             │
│         │   automation_events (event_type='confirmation_queued')     │
└─────────┼─────────────────────────────────────────────────────────────┘
          │
          │  ◄────────────────────────────────────────────────┐
          │                                                     │
          ▼                                                     │
┌─────────────────────────────────────────────────────────────┐│
│                    n8n (workflow "mesagerie")                ││
│                                                                ││
│  [Schedule Trigger, ex: la 15 min]                            ││
│       │                                                       ││
│       ├─► POST /api/automation/dispatch-review-requests ─────┼┘
│       │   (Laravel scanează departed/checked_out + 24h,       │
│       │    creează outbound_messages pt. recenzii)            │
│       │                                                        │
│       ├─► GET /api/automation/outbound-messages?status=pending│
│       │   (Laravel întoarce mesajele de trimis)               │
│       │                                                        │
│       ├─► [pentru fiecare mesaj: trimite pe canalul indicat   │
│       │    (whatsapp/telegram/viber/sms/email)]                │
│       │                                                        │
│       └─► POST /api/automation/outbound-messages/{id}/callback│
│           (status='sent'/'failed', error_message)             │
└────────────────────────────────────────────────────────────────┘
```

Toată logica de business (ce mesaj se trimite, către cine, cu ce text) trăiește în Laravel.
n8n rămâne "subțire": un trigger de schedule + 3 apeluri HTTP secvențiale + trimiterea
efectivă pe canal.

## Schema & șabloane de mesaje

### Migrare nouă

`database/migrations/2026_06_1X_000000_add_review_request_sent_to_lodging_reservations.php`

```php
Schema::table('lodging_reservations', function (Blueprint $table): void {
    $table->boolean('review_request_sent')->default(false)->after('status');
});
```

Simetrică cu `parking_reservations.review_request_sent`, deja existentă.

### Șabloane (`message_templates`, seed)

4 rânduri inițiale, `source='manual'`, `external_id=null`, `locale='ro'`, `is_active=true`,
`channel='whatsapp'` (operatorul poate schimba canalul/textul/limba direct din panou
ulterior — resursa Filament pentru `message_templates` există deja din subproiectul #2):

| `service` | `template_key` | `label` | `body` (placeholdere `{{...}}`) |
|---|---|---|---|
| `parking` | `confirmation` | Confirmare parcare | `Bună {{name}}! Rezervarea ta de parcare e confirmată: {{check_in}} - {{check_out}}, auto {{plate}}. Te așteptăm la Sky Center!` |
| `lodging` | `confirmation` | Confirmare cazare | `Bună {{guest_name}}! Rezervarea ta la {{property}} ({{room}}) e confirmată: {{check_in}} - {{check_out}}. Te așteptăm!` |
| `parking` | `review_request` | Cerere recenzie parcare | `Bună {{name}}! Mulțumim că ai parcat la Sky Center. Ne-ar ajuta enorm o recenzie: [link recenzie]` |
| `lodging` | `review_request` | Cerere recenzie cazare | `Bună {{guest_name}}! Mulțumim că ai stat la {{property}}. Ne-ar ajuta enorm o recenzie: [link recenzie]` |

### Randare

Acțiune nouă `app/Actions/Messaging/RenderMessageTemplate.php`:

- caută `MessageTemplate` activ după `(service, template_key, locale='ro')`
- înlocuiește `{{placeholder}}` cu valorile din rezervare/client (`str_replace`)
- `check_in`/`check_out`: format `d.m.Y H:i` pentru parcare (au oră), `d.m.Y` pentru cazare
  (doar dată)
- dacă nu există șablon activ → nu se creează `outbound_message`; se loghează
  `automation_events` cu `event_type='message_template_missing'` (operatorul vede gap-ul în
  Sistem > Automatizări, fără ca salvarea rezervării să eșueze)

## Mesaje de confirmare (observers)

### Trigger

Doi observeri noi, înregistrați în `AppServiceProvider::boot()`:

- `app/Observers/ParkingReservationObserver.php` — ascultă `created` și `updated` pe
  `ParkingReservation`
- `app/Observers/LodgingReservationObserver.php` — ascultă `created` și `updated` pe
  `LodgingReservation`

Condiție de declanșare (identică în ambii, doar statusul țintă diferă):

```php
// Parking: status țintă = 'booked'
// Lodging:  status țintă = 'confirmed'

public function created(ParkingReservation $reservation): void
{
    if ($reservation->status === 'booked') {
        $this->queueConfirmation->handle('parking', $reservation);
    }
}

public function updated(ParkingReservation $reservation): void
{
    if ($reservation->wasChanged('status')
        && $reservation->status === 'booked'
        && $reservation->getOriginal('status') !== 'booked') {
        $this->queueConfirmation->handle('parking', $reservation);
    }
}
```

Astfel, atât o rezervare creată direct cu `booked` (introducere manuală), cât și o tranziție
`pending_approval -> booked`, declanșează confirmarea — o singură dată per tranziție (nu
retrimite la salvări ulterioare care păstrează `booked`). Analog pentru `LodgingReservation`
cu statusul țintă `confirmed`.

### Acțiune comună: `app/Actions/Messaging/QueueConfirmationMessage.php`

1. Rezolvă datele de contact, în funcție de canalul șablonului activ:
   - canal `whatsapp` / `telegram` / `viber` / `sms` → folosește `normalized_phone`
     (pentru `telegram`/`viber`, n8n e responsabil să mapeze numărul de telefon la
     chat ID-ul corespunzător; Laravel trimite mereu numărul normalizat)
   - canal `email` → folosește `email`
   - **parking**: ambele câmpuri vin din `$reservation->customer`
     (`ParkingCustomer::normalized_phone` / `::email`)
   - **lodging**: ambele câmpuri vin direct din rezervare
     (`LodgingReservation::normalized_phone` / `::email`)
2. Dacă lipsește câmpul de contact corespunzător canalului → nu creează mesajul; creează
   `automation_events` cu `event_type='message_contact_missing'`, `service`, `external_id`.
3. Altfel: `RenderMessageTemplate` → text + canal
4. Creează `OutboundMessage`:
   - `service` = `parking`/`lodging`, `reference_id` = id-ul rezervării
   - `channel` = canalul șablonului activ
   - `template_key` = `confirmation`
   - `payload` = `{text, contact, reservation_id}`
   - `scheduled_at` = now(), `status` = `pending`
5. Creează `automation_events` (`webhook_log_id=null`, `event_type='confirmation_queued'`,
   `service`, `external_id`, `occurred_at=now()`, `payload`)

## Dispatch cereri de recenzie (`POST /api/automation/dispatch-review-requests`)

### Acțiune: `app/Actions/Automation/DispatchReviewRequests.php`

`handle(): array`

1. **Parcare**: `ParkingReservation::where('status', 'departed')
   ->where('check_out_at', '<=', now()->subHours(24))
   ->where('review_request_sent', false)->get()`
2. **Cazare**: `LodgingReservation::where('status', 'checked_out')
   ->where('check_out', '<=', now()->subDay()->toDateString())
   ->where('review_request_sent', false)->get()`
   (`check_out` e doar dată — "24h de la check-out" înseamnă că ziua de check-out a trecut)
3. Pentru fiecare rezervare găsită:
   - rezolvă contactul (ca la confirmare)
   - dacă lipsește contactul → `automation_events` cu `event_type='message_contact_missing'`,
     **nu** marchează `review_request_sent`, sare la următoarea
   - altfel: `RenderMessageTemplate` (`template_key='review_request'`) → creează
     `OutboundMessage` (`status='pending'`), marchează `review_request_sent=true`, creează
     `automation_events` (`event_type='review_request_queued'`)
4. Returnează `{'parking_queued' => N, 'lodging_queued' => M, 'skipped' => K}`

### Controller + rută

`app/Http/Controllers/Api/Automation/DispatchReviewRequestsController.php`, în grupul
`automation.token`:

```php
Route::post('dispatch-review-requests', DispatchReviewRequestsController::class);
```

### Logging

Ca la flux #1: înregistrează `automation_webhook_logs` (`endpoint='dispatch-review-requests'`,
`event_type='review_request_dispatch'`, `status='processed'`, `http_status=200`,
`response_body` = rezultatul de mai sus, `payload=null` — nu are body de intrare).

Idempotent prin natura sa: rulat de mai multe ori nu retrimite recenzii deja marcate
`review_request_sent=true`.

## API mesaje outbound (listare + callback)

### `GET /api/automation/outbound-messages?status=pending`

`app/Http/Controllers/Api/Automation/OutboundMessagesController.php`

- filtrează după `status` (implicit `pending`), `orderBy('scheduled_at')`, `limit(50)`
- răspuns "plat" (n8n nu trebuie să sape în `payload`):

```json
{
  "data": [
    {
      "id": 42,
      "service": "parking",
      "reference_id": 17,
      "channel": "whatsapp",
      "template_key": "confirmation",
      "text": "Bună Ion! Rezervarea ta de parcare e confirmată: ...",
      "contact": "+40712345678",
      "scheduled_at": "2026-06-10T10:00:00+03:00"
    }
  ]
}
```

- nu se loghează în `automation_webhook_logs` (e doar citire, fără efecte secundare) —
  consistent cu flux #1, unde request-urile fără side-effects nu se loghează.

### `POST /api/automation/outbound-messages/{id}/callback`

`app/Http/Controllers/Api/Automation/OutboundMessageCallbackController.php`

Body trimis de n8n:
```json
{ "status": "sent", "error_message": null }
```
sau
```json
{ "status": "failed", "error_message": "WhatsApp API timeout" }
```

Logică:

1. `404` dacă `outbound_message` nu există.
2. **Idempotență**: dacă mesajul nu mai e `pending` (deja procesat) → `200 OK` fără efecte
   (no-op), util la retry-uri n8n.
3. Altfel:
   - `status='sent'` → `sent_at = now()`, `status = 'sent'`
   - `status='failed'` → `status = 'failed'`, adaugă `error_message` în `payload` (tabelul
     `outbound_messages` nu are coloană dedicată pentru eroare)
   - rezolvă `external_id` al rezervării sursă
     (`ParkingReservation`/`LodgingReservation::find(reference_id)->external_id`, `null` dacă
     rezervarea nu mai există)
   - creează `automation_events` (`event_type = 'message_sent'` / `'message_failed'`,
     `service`, `external_id`, `occurred_at=now()`, `payload`)
   - loghează `automation_webhook_logs` (`endpoint='outbound-messages-callback'`,
     `event_type`, `status='processed'`, `http_status=200`, `payload`=body request)
4. Răspuns: `{"status": "ok"}`

### Rute noi (în grupul `automation.token`, `routes/api.php`)

```php
Route::get('outbound-messages', OutboundMessagesController::class);
Route::post('outbound-messages/{outboundMessage}/callback', OutboundMessageCallbackController::class);
Route::post('dispatch-review-requests', DispatchReviewRequestsController::class);
```

Toate cele 3 rute folosesc middleware-ul `automation.token` existent (`Authorization: Bearer
<AUTOMATION_API_TOKEN>`), la fel ca rutele din flux #1. Token lipsă/invalid → `401`.

## Testare

Teste PHPUnit (Feature), urmând TDD.

**Confirmări:**

1. `ParkingReservation` `pending_approval -> booked` → creează `OutboundMessage`
   (`status='pending'`, text randat corect) + `automation_events('confirmation_queued')`
2. `ParkingReservation` creat direct cu `status='booked'` → la fel
3. `LodgingReservation` `pending -> confirmed` → la fel (`template_key='confirmation'`)
4. Tranziție către un alt status (`booked -> parked`, `confirmed -> checked_in`) → **nu**
   creează mesaj nou
5. Fără șablon activ pentru `(service, template_key, locale)` →
   `automation_events('message_template_missing')`, fără `OutboundMessage`
6. Fără telefon/email pe client/rezervare → `automation_events('message_contact_missing')`,
   fără `OutboundMessage`

**Dispatch recenzii:**

7. `ParkingReservation` `status='departed'`, `check_out_at` cu 25h în urmă,
   `review_request_sent=false` → creează `OutboundMessage` (`template_key='review_request'`)
   + marchează `review_request_sent=true`
8. Aceeași rezervare, rulat a doua oară → nu creează duplicat
9. `check_out_at` cu 12h în urmă → nu e eligibilă încă
10. `LodgingReservation` `status='checked_out'`, `check_out` = ieri → eligibilă; `check_out` =
    azi → nu

**API mesaje outbound:**

11. `GET /api/automation/outbound-messages?status=pending` → listă plată, ordonată după
    `scheduled_at`, max 50
12. `POST .../callback` cu `status=sent` → `outbound_messages.status='sent'`, `sent_at`
    setat, `automation_events('message_sent')`
13. `POST .../callback` cu `status=failed` → `status='failed'`, `error_message` în `payload`,
    `automation_events('message_failed')`
14. Callback pe mesaj deja procesat → `200` no-op, fără eveniment duplicat
15. Token lipsă/invalid pe oricare din cele 3 endpoint-uri noi → `401`

Workflow-urile n8n propriu-zise (trimiterea efectivă pe WhatsApp/Telegram/Viber/SMS/email)
sunt testate manual — în afara suitei PHPUnit (configurația n8n nu face parte din acest
repo).

## În afara scopului

- Trimiterea efectivă pe WhatsApp/Telegram/Viber/SMS/email (workflow n8n, testat manual).
- Traduceri multi-limbă pentru șabloane (ulterior, fără cod nou — operatorul adaugă rânduri
  în `message_templates`).
- Retry/backoff automat pentru mesaje `failed` (operatorul poate interveni manual din panou).
- Confirmare/recenzie pentru rezervări care sar peste statusurile intermediare în alt mod
  decât tranzițiile descrise.
- Rate limiting / "ore liniștite" pentru trimitere.
- Istoric/versionare șabloane.
- Parsing buget Telegram — flux separat (#3 din subproiectul #3).
