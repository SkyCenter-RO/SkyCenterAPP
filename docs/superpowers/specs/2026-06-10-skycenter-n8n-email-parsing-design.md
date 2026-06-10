# Sky Center — Integrare n8n: Parsing email → rezervări (Subproiect #3, Flux #1)

## Context

Acest spec acoperă primul flux din subproiectul #3 (Integrări n8n): preluarea automată a
rezervărilor de parcare și cazare din emailuri de confirmare, prin n8n, și introducerea lor
în baza de date a aplicației (subproiectul #2) ca rezervări în așteptare de validare.

Celelalte fluxuri din subproiectul #3 — mesagerie outbound (confirmări, departed+24h,
recenzii) și parsing buget Telegram — sunt subiectul unor specuri separate, ulterioare.

**Decizie privind AI:** nu se folosește AI (ChatGPT/Gemini) în acest flux. Parsing-ul este
determinist (regex/extracție HTML în n8n). AI rămâne o opțiune de fallback pentru o
iterație viitoare, dacă parsarea deterministă se dovedește insuficientă.

## Surse de email

1. **Formular propriu de rezervare parcare** (`source = 'parcare_form'`) — emailuri de
   confirmare generate de formularul de pe site-ul Sky Center, trimise către o adresă Gmail
   dedicată.
2. **Booking.com** (`source = 'booking_com'`) — emailuri de notificare rezervare nouă.
3. **Airbnb** (`source = 'airbnb'`) — emailuri de notificare rezervare nouă.

## Arhitectură

```
Gmail inbox ──(label/filtru)──> n8n workflow (per sursă)
                                    │
                                    ├─ Trigger: Gmail (email nou cu eticheta sursei)
                                    ├─ Parsare: nod Code/HTML, extracție regex determinist
                                    └─ HTTP Request ──> POST /api/automation/{service}-reservations
                                                             (Authorization: Bearer <token>)
                                                                  │
                                                                  ▼
                                                        Laravel app (subproiect #2)
                                                                  │
                                            ┌─────────────────────┴─────────────────────┐
                                            ▼                                             ▼
                                  automation_webhook_logs                      parking_reservations /
                                  (audit: payload, status,                     lodging_reservations
                                   event_type, error_message)                  (upsert pe source+external_id)
                                            │
                                            ▼
                                   automation_events
                                   (event_type derivat, ex:
                                    reservation_created/updated)
```

n8n rămâne "subțire": un singur trigger Gmail + un nod de parsare per sursă + un apel HTTP.
Toată logica de business (validare, găsire/creare client, upsert, idempotență) trăiește în
Laravel, unde poate fi acoperită cu teste PHPUnit.

## Endpoint-uri API

Fișier nou `routes/api.php`, montat sub prefixul `/api/automation`.

- `POST /api/automation/parking-reservations`
- `POST /api/automation/lodging-reservations`

### Autentificare

Middleware custom care verifică header-ul `Authorization: Bearer <token>` față de un token
static stocat în `.env` (`AUTOMATION_API_TOKEN`). Token-ul este configurat ca credențial în
n8n (HTTP Request node → Header Auth).

- Token lipsă/invalid → `401 Unauthorized`, fără înregistrare în `automation_webhook_logs`
  (nu reprezintă un apel legitim de automatizare).

## Logging & idempotență

Fiecare apel POST valid (cu token corect) este înregistrat în `automation_webhook_logs`:

| Câmp | Valoare |
|---|---|
| `endpoint` | `parking-reservations` / `lodging-reservations` |
| `service` | `parking` / `lodging` |
| `event_type` | `reservation` (parsare reușită) sau `unparsed` (parsare eșuată în n8n) |
| `external_id` | ID-ul rezervării din sursă (din payload) |
| `payload` | corpul JSON primit |
| `status` | `processed` sau `error` |
| `http_status` | codul de răspuns trimis |
| `error_message` | motivul eșecului (dacă `status = error`) |
| `received_at` / `processed_at` | timestamp-uri |

La procesare reușită, se creează și un rând în `automation_events`
(`webhook_log_id`, `event_type = 'reservation_created'` sau `'reservation_updated'`,
`service`, `external_id`, `occurred_at`, `payload`), legat de log. Aceste evenimente sunt
vizibile în resursa read-only **Sistem > Automatizări** din panou.

**Idempotență:** upsert pe `(source, external_id)` — payload-uri repetate pentru aceeași
rezervare actualizează rândul existent, nu creează duplicate.

## Mapare câmpuri per sursă

### Parcare formular (`source = 'parcare_form'`)

n8n extrage din email:
- client: `name`, `phone`, `email`
- rezervare: `plate`, `vehicle_type`, `check_in_at`, `check_out_at`, `adults`, `children`,
  `lot` / `zone` (dacă formularul le captează), `quoted_price`, `currency`
- `external_id`: ID-ul submisiei formularului / referința din email

Procesare Laravel:
1. Find-or-create `ParkingCustomer` după `normalized_phone` (normalizare telefon RO).
2. Upsert `ParkingReservation` pe `(source='parcare_form', external_id)`:
   - `customer_id` = clientul de mai sus
   - `lot_id` = lot-ul indicat în payload; dacă lipsește, se folosește lot-ul implicit
     configurat în `.env` (`AUTOMATION_DEFAULT_PARKING_LOT_ID`)
   - `status = 'pending_approval'`
   - restul câmpurilor mapate direct

### Booking.com (`source = 'booking_com'`) / Airbnb (`source = 'airbnb'`)

n8n extrage din email:
- `guest_name`, `phone`, `email` (telefonul/emailul pot fi mascate până la check-in)
- `check_in`, `check_out`, `nights`, `price`, `currency`
- `external_id`: numărul de rezervare al platformei

Procesare Laravel:
1. Upsert `LodgingReservation` pe `(source, external_id)`:
   - `room_id = null` (operatorul atribuie camera manual din panou)
   - `status = 'pending'`
   - restul câmpurilor mapate direct

## Tratarea emailurilor neparsabile

Dacă n8n nu poate extrage câmpurile minime necesare (ex: `external_id`, datele de
check-in/out), workflow-ul trimite totuși un POST cu:
- `event_type = 'unparsed'`
- `payload` = textul brut al emailului + metadate disponibile (subiect, expeditor, dată)

Laravel:
- înregistrează în `automation_webhook_logs` cu `status = 'error'`,
  `error_message` descriptiv
- **nu** creează nicio rezervare
- răspunde `422 Unprocessable Entity`

Operatorul vede intrarea în **Sistem > Automatizări** și introduce rezervarea manual din
panou.

## Testare

Teste PHPUnit (Feature) pentru ambele endpoint-uri, urmând TDD:

1. Payload valid `parcare_form` → se creează `ParkingCustomer` (dacă nu există) +
   `ParkingReservation` cu `status='pending_approval'` + log `processed` + event
   `reservation_created`.
2. Payload valid `booking_com`/`airbnb` → se creează `LodgingReservation` cu `room_id=null`,
   `status='pending'` + log `processed` + event `reservation_created`.
3. Payload duplicat (același `source`+`external_id`) → actualizează rândul existent, nu
   creează duplicat; event `reservation_updated`.
4. Payload `event_type='unparsed'` → log `status='error'`, nicio rezervare creată,
   răspuns `422`.
5. Lipsă/invalid bearer token → `401`, niciun log creat.

Workflow-urile n8n propriu-zise sunt testate manual cu emailuri de test/redirecționate —
în afara suitei PHPUnit (configurația n8n nu face parte din acest repo).

## În afara scopului

- Parsare AI (ChatGPT/Gemini) ca fallback — posibilă iterație viitoare.
- Mesagerie outbound (confirmări, departed+24h, recenzii) — flux separat (#2 din
  subproiectul #3).
- Parsing buget Telegram — flux separat (#3 din subproiectul #3).
- Atribuire automată a camerei pentru cazare — operatorul o face manual.
- Deduplicare avansată a clienților dincolo de match pe telefon normalizat.
