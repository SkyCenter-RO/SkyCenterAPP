# Sky Center — Design: Marketing Intelligence (Subproiect #6)

- **Data:** 2026-06-11
- **Autor:** brainstorming cu utilizatorul
- **Status:** aprobat
- **Subproiect:** #6 din roadmap — Marketing Intelligence Panel

---

## 1. Context și scop

Sky Center rulează campanii publicitare pe mai multe platforme (Google Ads, Facebook/Instagram, TikTok)
cu un buget de €300/lună. Recenziile sunt distribuite pe 4+ platforme (Google, Booking, Facebook,
TripAdvisor). Conținutul social este planificat manual.

Scopul acestui subproiect este un **panel de Marketing Intelligence** în Filament care:
- Centralizează tracking-ul campaniilor publicitare (introducere manuală)
- Monitorizează scorul de recenzii per platformă
- Organizează calendarul de conținut social media
- Oferă o imagine de ansamblu asupra stării fiecărui canal de marketing
- Înregistrează cheltuielile zilnice cu publicitatea

**Acces:** Admin only (date financiare sensibile).
**Automatizare:** Faza 2 (n8n auto-reply recenzii, auto-posting social) — deferată.

---

## 2. Decizii de design confirmate

| Decizie | Valoare |
|---|---|
| Acces | Admin only |
| Introducere date | Manuală (fără API extern acum) |
| Buget total | €300/lună, alocat pe platforme |
| Platforme ads | Google (50%), Facebook/Instagram (25%), TikTok/LSA (15%), Hotel Ads (10%) |
| Verticale business | Parcare, Hotel, Rent-a-car |
| Status secțiune | „În lucru" — vizibil în UI cu badge |
| Automatizare | Faza 2 (n8n), deferred |

---

## 3. Module (5 resurse Filament)

### 3.1 `marketing_campaigns` — Campanii

Tracking manual al campaniilor active per platformă și lună.

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | ex: `PMax - RO`, `FB - Parcare Juin` |
| platform | varchar(64) | CHECK: `google`, `facebook`, `instagram`, `tiktok`, `bing`, `other` |
| vertical | varchar(32) | CHECK: `parcare`, `hotel`, `rent`, `bundle`, `general` |
| status | varchar(32) | CHECK: `active`, `paused`, `completed`, `draft` |
| budget_eur | numeric(10,2) NULL | buget alocat în EUR |
| spend_eur | numeric(10,2) NULL | cheltuieli reale |
| conversions | integer NULL | nr. conversii (apeluri/rezervări) |
| cpc_eur | numeric(8,4) NULL | cost per click mediu |
| roas | numeric(8,2) NULL | Return on Ad Spend |
| period_month | date | prima zi a lunii |
| notes | text NULL | |
| created_by_id | bigint NULL FK users | |
| created_at / updated_at | timestamptz | |

### 3.2 `marketing_ad_spend_logs` — Jurnal Cheltuieli

Cheltuieli zilnice per platformă pentru tracking vs. buget lunar.

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| campaign_id | bigint NULL FK marketing_campaigns | |
| platform | varchar(64) | |
| vertical | varchar(32) NULL | |
| amount_eur | numeric(10,2) | |
| spent_on | date index | |
| notes | text NULL | |
| created_by_id | bigint NULL FK users | |
| created_at / updated_at | timestamptz | |

### 3.3 `marketing_reviews` — Tracker Recenzii

Scoruri de recenzii per platformă, înregistrate periodic (săptămânal/lunar).

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| platform | varchar(64) | CHECK: `google`, `booking`, `facebook`, `tripadvisor`, `airbnb` |
| vertical | varchar(32) NULL | hotel/parcare/rent/all |
| score | numeric(3,2) | 1.00–10.00 (Booking folosește /10, Google /5) |
| review_count | integer NULL | nr. total recenzii |
| recorded_on | date index | data înregistrării |
| notes | text NULL | |
| created_by_id | bigint NULL FK users | |
| created_at | timestamptz | |

### 3.4 `marketing_content_calendar` — Calendar Conținut

Planificarea postărilor organice pe social media.

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| title | varchar(255) | titlul postării |
| platform | varchar(64) | CHECK: `facebook`, `instagram`, `tiktok`, `all` |
| vertical | varchar(32) NULL | parcare/hotel/rent/bundle |
| content_type | varchar(64) | CHECK: `photo`, `reel`, `story`, `carousel`, `text` |
| language | varchar(8) | `ro`, `en`, `it`, `ru`, etc. |
| status | varchar(32) | CHECK: `idea`, `in_progress`, `ready`, `scheduled`, `published`, `cancelled` |
| scheduled_at | date NULL | data planificată |
| published_at | date NULL | data publicării |
| copy_text | text NULL | textul postării |
| notes | text NULL | |
| created_by_id | bigint NULL FK users | |
| created_at / updated_at | timestamptz | |

### 3.5 `marketing_channels` — Status Canale

Starea fiecărui canal de marketing (configurat o dată, actualizat periodic).

| Coloană | Tip | Note |
|---|---|---|
| id | bigint PK | |
| name | varchar(128) | ex: `Google Business Profile`, `Booking.com`, `TikTok` |
| channel_type | varchar(64) | CHECK: `ads`, `seo`, `social`, `listing`, `affiliate`, `email` |
| status | varchar(32) | CHECK: `active`, `setup_needed`, `paused`, `monitoring`, `blocked` |
| url | text NULL | link la canal |
| account_id | varchar(255) NULL | ID cont extern |
| monthly_budget_eur | numeric(10,2) NULL | buget alocat |
| notes | text NULL | observații și next steps |
| last_reviewed_at | date NULL | ultima revizuire |
| created_at / updated_at | timestamptz | |

---

## 4. UI Filament

### Grup navigare: 📢 Marketing (Admin only)

| Resursă | Icon | Label |
|---|---|---|
| Campanii | `heroicon-o-megaphone` | Campanii |
| Jurnal Cheltuieli | `heroicon-o-banknotes` | Cheltuieli Ads |
| Recenzii | `heroicon-o-star` | Recenzii |
| Calendar Conținut | `heroicon-o-calendar-days` | Calendar Conținut |
| Canale | `heroicon-o-signal` | Canale Marketing |

### Badge „În lucru"
Fiecare pagină index afișează un `InfolistEntry` sau banner galben care marchează secțiunea
ca „Automatizare în lucru — date introduse manual" pentru a semnala Faza 2.

---

## 5. Relație cu Faza 2 (n8n — deferred)

Structura DB este pregătită pentru automatizare viitoare:
- `marketing_reviews` va fi populat automat de un workflow n8n care parsează Google My Business API
- `marketing_content_calendar.status` va fi actualizat de n8n după auto-posting pe Facebook/Instagram
- `marketing_campaigns` va primi date din Google Ads API via n8n
- `marketing_ad_spend_logs` va fi populat din Google Ads Reporting API

---

## 6. Testare

TDD standard: teste Feature pentru CRUD pe fiecare resursă, acces admin-only verificat
(operatorul nu poate accesa), filtrare pe `period_month` și `platform`.
Rulare: `docker compose exec -T app php artisan test`
