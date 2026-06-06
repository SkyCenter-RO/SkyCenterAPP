# Sky Center — Design schemă bază de date (PostgreSQL 16)

- **Data:** 2026-06-06
- **Autor:** brainstorming cu utilizatorul (infinitive.gen@gmail.com)
- **Status:** aprobat pe secțiuni, în așteptarea revizuirii finale a spec-ului
- **Subproiect:** #1 din roadmap — Fundația: baza de date SQL

---

## 1. Context și scop

Sky Center (https://skycenter.ro/) este o afacere cu trei servicii principale:
**parcare**, **cazare** (hotel) și **rent a car**. Se construiește o aplicație internă
nouă (web app întâi, apoi Android — fără iOS), care rulează **local prin Docker** pe
`localhost`. Multe fluxuri se vor lega ulterior de **n8n local** (webhook-uri/endpoint-uri):
parsing email (Gmail) pentru confirmări, mesaje WhatsApp/Telegram/Viber, bot Telegram pentru buget.

Acest spec acoperă **doar baza de date** (subproiectul #1). Aplicația web, integrările n8n,
orarul/turele, marketingul și Android sunt subproiecte separate, ulterioare.

### Relația cu proiectul `Ops`

Există deja un proiect vecin, `D:\Automation\SkyPark\Ops` (Laravel 12 + Filament 5 +
PostgreSQL 16, migrat din AppSheet), care conține o schemă matură pentru aceleași domenii.
Decizia: **aplicația nouă este independentă**, dar **preia deciziile de design bune din Ops**.
Nu ne legăm de codul sau baza de date Ops; refolosim înțelepciunea schemei.

### Decizii confirmate

| Decizie | Valoare |
|---|---|
| Motor DB | PostgreSQL 16, în Docker, local |
| Monedă | RON implicit, cu coloană `currency` pentru EUR unde e nevoie |
| Relația cu Ops | App nou, independent, preia design-ul din Ops |
| Acoperire spec | parcare + cazare + rent a car + buget + tabele comune |
| Stack implementare | Laravel 12 + migrări/Eloquent (ca Ops) |
| Parcarea 1 | 54 locuri (A=11, B=8, C=14, D=12, E=9) |
| Parcarea 2 | 30 locuri |

### În afara scopului (subproiecte ulterioare)

- Aplicația web (UI, „ordinea de zi", grafice de disponibilitate)
- Fluxurile n8n efective (parsing, trimitere mesaje)
- Orar/ture (upload Excel, 4 oameni × 12h)
- Marketing (FB/IG/TikTok, reclame Google)
- Aplicația Android

### De atașat ulterior de utilizator

- Tabelul de prețuri parcare (vehicul × nr. zile) → populează `parking_prices`
- Harta parcării din Canvas → populează `parking_spaces` (poziții XY)
- Formatul email-urilor de confirmare (Gmail) → ghidează parsing-ul n8n
- Linkurile iCal de sincronizare Booking/Airbnb → populează `lodging_sync_links`

---

## 2. Convenții generale

Aplicate aproape tuturor tabelelor de date (preluate din Ops, optimizate pentru n8n):

- `id` — `BIGINT GENERATED ALWAYS AS IDENTITY` (cheie primară).
- `source` — `VARCHAR(64) NOT NULL DEFAULT 'manual'` — originea rândului
  (`manual`, `gmail`, `whatsapp`, `telegram`, `booking`, `airbnb`, ...).
- `external_id` — `VARCHAR(190) NULL` — id-ul din sistemul sursă.
- **Cheie unică `(source, external_id)`** pe tabelele alimentate din n8n → **upsert idempotent**
  (același email/mesaj procesat de două ori nu creează duplicat).
- `metadata` — `JSONB NULL` — câmpuri suplimentare fără migrare.
- `created_by_id` / `updated_by_id` — `BIGINT NULL REFERENCES users(id)` — audit autor.
- `created_at` / `updated_at` — `TIMESTAMPTZ NOT NULL DEFAULT now()`.

**Tipuri standard:**

- Bani: `NUMERIC(12,2)`. Monedă: `currency VARCHAR(3) NOT NULL DEFAULT 'RON'`.
- Telefon: `phone VARCHAR(64)`, plus `normalized_phone VARCHAR(64)` indexat (E.164 fără spații).
- Numere matricole: `plate VARCHAR(64)`, plus `normalized_plate VARCHAR(64)` indexat.
- Statusuri: `VARCHAR` cu **CHECK constraint** pe valorile permise (mai ușor de evoluat decât
  tipuri ENUM Postgres; valorile permise sunt documentate la fiecare tabel).
- Date calendaristice fără oră: `DATE`. Momente cu oră: `TIMESTAMPTZ`.

---

## 3. Tabele comune

### `users`
Personalul intern (operatorii din ture) + autentificare.

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | |
| email | varchar(255) unique | login |
| phone | varchar(64) | |
| role | varchar(32) | CHECK: `admin`, `operator` |
| is_active | boolean default true | |
| password | varchar(255) | hash |
| created_at / updated_at | timestamptz | |

### `payments`
Plăți pentru oricare serviciu (referințe nullable către fiecare domeniu).

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| source, external_id, metadata | conform convenției | |
| service | varchar(32) | CHECK: `parking`, `lodging`, `rent` |
| parking_reservation_id | bigint NULL FK | nullOnDelete |
| lodging_reservation_id | bigint NULL FK | nullOnDelete |
| rent_contract_id | bigint NULL FK | nullOnDelete |
| amount | numeric(12,2) default 0 | |
| currency | varchar(3) default 'RON' | |
| method | varchar(32) | ex: `cash`, `card`, `transfer` |
| paid_at | timestamptz NULL | |
| notes | text | |
| created_by_id, updated_by_id | bigint NULL FK users | |
| created_at / updated_at | timestamptz | |

Index: `(service, paid_at)`, `(method)`, `(source, external_id)`.

### `message_templates`
Șabloane pentru confirmări și follow-up.

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| template_key | varchar(190) | ex: `parking_departed_review` |
| service | varchar(64) NULL | `parking`/`lodging`/`rent` |
| channel | varchar(64) | CHECK: `whatsapp`, `telegram`, `viber`, `email`, `sms` |
| locale | varchar(16) | ex: `ro` |
| label | varchar(255) NULL | |
| body | text | conținut cu placeholder-e |
| is_active | boolean default true | |

Unique: `(source, external_id)`. Index: `(service, channel)`, `(channel, locale)`.

### `outbound_messages`
Coadă de mesaje programate (ex: **departed + 24h** → recenzie).

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| service | varchar(32) | |
| reference_id | bigint NULL | id-ul entității (ex: parking_reservation) |
| channel | varchar(64) | whatsapp/telegram/viber/email |
| template_key | varchar(190) NULL | |
| payload | jsonb NULL | variabile pentru șablon |
| scheduled_at | timestamptz | când trebuie trimis |
| sent_at | timestamptz NULL | |
| status | varchar(32) default 'pending' | CHECK: `pending`, `sent`, `failed`, `cancelled` |
| created_at / updated_at | timestamptz | |

Index: `(status, scheduled_at)`.

### `automation_webhook_logs`
Log brut al webhook-urilor n8n.

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| endpoint | varchar(255) | |
| idempotency_key | varchar(191) NULL | |
| status | varchar(32) | ex: `received`, `processed`, `error` |
| http_status | smallint | |
| event_type | varchar(120) NULL | |
| service | varchar(32) NULL | |
| external_id | varchar(190) NULL | |
| payload | jsonb NULL | |
| response_body | jsonb NULL | |
| error_message | varchar(255) NULL | |
| received_at | timestamptz default now() | |
| processed_at | timestamptz NULL | |

Index: `(endpoint, idempotency_key)`, `(status, received_at)`.

### `automation_events`
Evenimente normalizate extrase din webhook-uri.

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| webhook_log_id | bigint NULL FK | nullOnDelete |
| event_type | varchar(120) | |
| service | varchar(32) NULL | |
| external_id | varchar(190) NULL | |
| occurred_at | timestamptz NULL | |
| status | varchar(32) default 'received' | |
| payload | jsonb NULL | |

Index: `(service, event_type)`, `(occurred_at)`.

---

## 4. Parcare

### `parking_lots`
| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| name | varchar(128) | `Parcarea 1`, `Parcarea 2` |
| total_spaces | integer NULL | 54, respectiv 30 |
| notes | text | |

### `parking_zones`
| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| lot_id | bigint FK parking_lots | |
| code | varchar(16) | `A`–`E` (doar Parcarea 1) |
| capacity | integer | A=11, B=8, C=14, D=12, E=9 |

### `parking_spaces`
Locuri individuale (din harta Canvas; opțional de detaliat).

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| source, external_id, metadata | convenție | |
| lot_id | bigint FK parking_lots | |
| zone_id | bigint NULL FK parking_zones | |
| label | varchar(64) NULL | eticheta locului |
| requires_keys | boolean default false | |
| vehicle_type_suitability | varchar(128) NULL | |
| blocks_space_id | bigint NULL | locul pe care îl blochează |
| blocked_by_space_id | bigint NULL | locul care îl blochează |
| xy_map_location | text NULL | poziție pe hartă |
| notes | text | |

### `parking_customers`
| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| source, external_id, metadata | convenție | |
| name | varchar(255) NULL | |
| phone | varchar(64) NULL | |
| normalized_phone | varchar(64) NULL index | |
| email | varchar(255) NULL index | |
| city | varchar(255) NULL | orașul zborului (plecare/întoarcere) |

Unique: `(source, external_id)`.

### `parking_prices`
Tabelul de prețuri (vehicul × nr. zile) — populat ulterior de utilizator.

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| source, external_id, metadata | convenție | |
| vehicle_type | varchar(64) | CHECK: `autoturism`, `SUV`, `dubă` |
| min_days | integer NULL | |
| max_days | integer NULL | |
| price_per_day | numeric(10,2) NULL | |
| fixed_price | numeric(10,2) NULL | |
| currency | varchar(3) default 'RON' | |

### `parking_reservations`
| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| source, external_id, metadata | convenție | sursă: gmail/whatsapp/manual |
| customer_id | bigint NULL FK parking_customers | |
| lot_id | bigint NULL FK parking_lots | |
| zone_id | bigint NULL FK parking_zones | |
| parking_space_id | bigint NULL FK parking_spaces | |
| status | varchar(32) | CHECK: `pending_approval`, `booked`, `parked`, `departed`, `cancelled` |
| plate | varchar(64) NULL | |
| normalized_plate | varchar(64) NULL index | |
| vehicle_type | varchar(64) NULL | `autoturism`/`SUV`/`dubă` |
| check_in_at | timestamptz NULL index | |
| check_out_at | timestamptz NULL index | |
| days | numeric(6,2) NULL | |
| adults | integer NULL | |
| children | integer NULL | |
| keys_left | boolean default false | au lăsat cheile? |
| cost | numeric(12,2) NULL | calculat din tabelul de prețuri |
| quoted_price | numeric(12,2) NULL | |
| currency | varchar(3) default 'RON' | |
| paid | boolean default false | |
| notes | text | |
| review_request_sent | boolean default false | follow-up +24h trimis? |
| source_created_at | timestamptz NULL | când a venit confirmarea |
| created_by_id, updated_by_id | bigint NULL FK users | |

Index: `(customer_id, status)`, `(status, check_in_at)`, `(status, check_out_at)`.
Unique: `(source, external_id)`.

**Statusuri:** `pending_approval` (email de confirmare venit pe Gmail, așteaptă bifare) →
`booked` (confirmat) → `parked` (mașina a sosit) → `departed` (check-out) → eventual `cancelled`.

### `parking_reservation_images`
Minim 5 imagini per rezervare.

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| parking_reservation_id | bigint FK cascadeOnDelete | |
| path | text | |
| caption | varchar(255) NULL | |
| created_at | timestamptz | |

### `parking_status_audits`
Istoricul de status — declanșează follow-up-ul de 24h.

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| parking_reservation_id | bigint FK cascadeOnDelete | |
| user_id | bigint NULL FK users | |
| from_status | varchar(32) NULL | |
| to_status | varchar(32) | |
| changed_at | timestamptz default now() | |
| notes | text NULL | |

**Flux departed + 24h:** la trecerea în `departed`, se scrie un rând de audit; un job (sau n8n)
creează un rând în `outbound_messages` programat la `changed_at + 24h` (canal email + WhatsApp/
Telegram/Viber) cu întrebarea „cum ai aflat de noi" + cerere de recenzie FB/Google Maps.

---

## 5. Cazare

### `lodging_properties`
| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| source, external_id, metadata | convenție | |
| name | varchar(255) | `Sky Center`, `Serafim` |
| slug | varchar(190) NULL | |
| is_active | boolean default true | |
| notes | text | |

### `rooms`
Sky Center = 7 camere, Serafim = 5 camere.

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| source, external_id, metadata | convenție | |
| property_id | bigint FK lodging_properties | |
| name | varchar(255) | numele camerei |
| is_active | boolean default true | |
| notes | text | |

Unique: `(source, external_id)`. Index: `(property_id, name)`.

### `lodging_reservations`
| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| source, external_id, metadata | convenție | `booking`/`airbnb`/`direct` |
| room_id | bigint NULL FK rooms | |
| guest_name | varchar(255) NULL | |
| phone | varchar(64) NULL | |
| normalized_phone | varchar(64) NULL index | |
| email | varchar(255) NULL index | |
| status | varchar(32) NULL | CHECK: `pending`, `confirmed`, `checked_in`, `checked_out`, `cancelled` |
| check_in | date NULL index | |
| check_out | date NULL index | |
| nights | integer NULL | = check_out − check_in (calculat) |
| price | numeric(12,2) NULL | prețul de pe Booking/Airbnb |
| direct_price | numeric(12,2) NULL | prețul când sună direct |
| currency | varchar(3) default 'RON' | |
| source_created_at | timestamptz NULL | când a venit confirmarea |
| notes | text | |

Index: `(room_id, status)`, `(room_id, check_in)`, `(room_id, check_out)`.
Unique: `(source, external_id)`.

### `lodging_sync_links`
Linkuri iCal de sincronizare (delay 5–7h) — completate ulterior.

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| property_id | bigint NULL FK lodging_properties | |
| room_id | bigint NULL FK rooms | |
| channel | varchar(32) | CHECK: `booking`, `airbnb` |
| ical_url | text | |
| last_synced_at | timestamptz NULL | |
| is_active | boolean default true | |

---

## 6. Rent a car

### `rent_vehicles`
| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| source, external_id, metadata | convenție | |
| license_plate | varchar(64) NULL index | nr. înmatriculare |
| chassis_vin | varchar(190) NULL | serie șasiu |
| brand | varchar(128) NULL | marca |
| model_name | varchar(128) NULL | model |
| manufacture_year | smallint NULL | an fabricație |
| tire_type | varchar(128) NULL | tip anvelope |
| insurance_start_date | date NULL | |
| insurance_end_date | date NULL | |
| insurance_12_months | boolean default false | asigurare 12 luni da/nu |
| itp_date | date NULL | |
| itp_expiry_date | date NULL | |
| current_km | integer NULL | |
| monthly_rent_price | numeric(12,2) NULL | preț chirie lunar |
| daily_rent_price | numeric(12,2) NULL | preț chirie zilnic |
| warranty_standard | numeric(12,2) NULL | garanție standard |
| currency | varchar(3) default 'RON' | |
| status | varchar(32) | CHECK: `available`, `rented`, `service` |
| notes | text | |

Index: `(status)`, `(itp_expiry_date)`, `(insurance_end_date)`.

### `rent_vehicle_images`
| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| rent_vehicle_id | bigint FK cascadeOnDelete | |
| path | text | |
| caption | varchar(255) NULL | |

### `rent_clients`
| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| source, external_id, metadata | convenție | |
| name | varchar(255) NULL index | |
| phone | varchar(64) NULL | |
| normalized_phone | varchar(64) NULL index | |
| email | varchar(255) NULL | |
| identity_document | varchar(190) NULL | |
| notes | text | |

### `rent_contracts`
Mașina iese din flotă pe un contract.

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| source, external_id, metadata | convenție | |
| contract_code | varchar(190) NULL | ID contract |
| rent_vehicle_id | bigint NULL FK rent_vehicles | |
| rent_client_id | bigint NULL FK rent_clients | |
| usage_type | varchar(32) | CHECK: `rent`, `uber`, `bolt` |
| start_date | date NULL index | |
| end_date | date NULL index | |
| km_at_handover | integer NULL | km la predare |
| km_at_return | integer NULL | km la returnare |
| daily_price | numeric(12,2) NULL | pt. `rent` |
| monthly_price | numeric(12,2) NULL | pt. `uber`/`bolt` |
| warranty_collected | numeric(12,2) NULL | garanție încasată (pt. `rent`) |
| total_price | numeric(12,2) NULL | calculat |
| currency | varchar(3) default 'RON' | |
| status | varchar(32) | CHECK: `active`, `completed`, `cancelled` |
| notes | text | |

Index: `(rent_vehicle_id, status)`, `(status, end_date)`.

**Regulă de preț:** `usage_type = rent` → calcul **zilnic** + garanție; `uber`/`bolt` → preț **lunar**.

### `rent_maintenance_records`
| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| rent_vehicle_id | bigint FK rent_vehicles | |
| service_at | timestamptz NULL index | |
| mileage_at_service | integer NULL | |
| intervention_type | varchar(190) NULL | |
| next_service_km | integer NULL | |
| details | text | |

---

## 7. Buget

### `budget_categories`
Categorii configurabile (apă, lumină, gaz, electricitate, inventar, ...).

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| service | varchar(32) | CHECK: `hotel`, `parcare`, `rent`, `general` |
| name | varchar(190) | ex: `apă`, `lumină`, `gaz`, `inventar` |
| kind | varchar(16) | CHECK: `expense`, `income` |
| frequency | varchar(16) | CHECK: `daily`, `weekly`, `monthly`, `quarterly`, `yearly`, `once` |
| default_amount | numeric(12,2) NULL | |
| currency | varchar(3) default 'RON' | |
| is_active | boolean default true | |

### `budget_transactions`
Cheltuieli + încasări.

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| source, external_id, metadata | convenție | telegram/manual/email |
| type | varchar(16) | CHECK: `income`, `expense` |
| category_id | bigint NULL FK budget_categories | |
| service | varchar(32) NULL | hotel/parcare/rent/general |
| amount | numeric(12,2) | |
| currency | varchar(3) default 'RON' | |
| occurred_on | date | |
| description | text | |
| telegram_chat | varchar(32) NULL | `expenses`/`income` |
| raw_message_id | bigint NULL FK budget_raw_messages | |
| created_by_id | bigint NULL FK users | |

Index: `(type, occurred_on)`, `(category_id)`, `(service, occurred_on)`.

### `budget_raw_messages`
Mesajele brute de pe Telegram (parsing idempotent, formule Python, fără AI).

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| chat_id | varchar(64) | grupul de cheltuieli/încasări |
| message_id | varchar(64) | unic per chat |
| text | text | |
| parsed | boolean default false | |
| transaction_id | bigint NULL FK budget_transactions | |
| received_at | timestamptz default now() | |

Unique: `(chat_id, message_id)`.

### `salaries`
| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| user_id | bigint NULL FK users | |
| employee_name | varchar(255) NULL | dacă nu e user în sistem |
| amount | numeric(12,2) | |
| currency | varchar(3) default 'RON' | |
| period_month | date | prima zi a lunii |
| paid_at | timestamptz NULL | |
| status | varchar(32) default 'pending' | CHECK: `pending`, `paid` |
| notes | text | |

---

## 8. Fluxuri-cheie care folosesc schema

1. **Parcare „pending → confirmare":** email pe Gmail → n8n → upsert `parking_reservations`
   cu `status = pending_approval`. Operatorul bifează → `booked`. Trimitem confirmare
   (WhatsApp/n8n) via `outbound_messages` + `message_templates`.
2. **Departed + 24h:** check-out → `status = departed` + rând în `parking_status_audits` →
   `outbound_messages` programat la +24h (email + WhatsApp/Telegram/Viber): „cum ai aflat de noi" +
   recenzie FB/Google.
3. **Cazare:** confirmare Booking/Airbnb (email instant) → `lodging_reservations`;
   `lodging_sync_links` (iCal, delay 5–7h) ține calendarul aliniat.
4. **„Ordinea de zi" (toate serviciile):** interogări pe `check_in_at`/`check_out_at`/`start_date`/
   `end_date` pentru azi/mâine/poimâine + filtrare pe dată aleasă pentru disponibilitate.
5. **Buget Telegram:** 2 grupuri (cheltuieli/încasări) → n8n → `budget_raw_messages` →
   formule Python → `budget_transactions` legat de `budget_categories`. Eșec parsare ⇒ rămâne
   `parsed = false` pentru completare manuală.

---

## 9. Întrebări deschise / de validat la implementare

- Seed inițial: 2 loturi, zonele A–E ale Parcării 1, 2 proprietăți cazare cu 7+5 camere,
  câteva `message_templates` de bază, categorii de buget standard.
- Normalizarea telefonului/plăcuței: regulile exacte se stabilesc la implementare (E.164 pt. telefon).
- Dacă apar mai multe canale de plată/usage_type, se extind valorile CHECK prin migrare.
