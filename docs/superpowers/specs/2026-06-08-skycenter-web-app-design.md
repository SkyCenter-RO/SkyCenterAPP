# Sky Center — Web App Intern (Subproiect #2) — Design

## Context

Acesta e al doilea din cele 7 subproiecte ale aplicației interne Sky Center (parcare, cazare, rent-a-car + buget). Subproiectul #1 (baza de date PostgreSQL prin Laravel 12, ramura `feat/database-foundation`, deja unită în `master`) a livrat schema completă: ~24 tabele de domeniu (parcare, cazare, rent-a-car, plăți, buget, mesagerie/automatizare, utilizatori) plus seederi de referință.

Acest subproiect construiește **interfața web internă** peste schema existentă — un panou de administrare prin care echipa Sky Center gestionează zilnic toate cele trei servicii și bugetul, fără a mai folosi hârtii sau tabele separate.

## Arhitectură & stack

- **Filament 5** peste Laravel 12 (deja prezent în repo) — panou de administrare unic, fără frontend separat.
- Autentificare standard Filament (login + sesiune Laravel) — nu se construiește API separat. Integrarea n8n (subproiectul #3) se va conecta direct la baza de date / endpoint-uri dedicate, nu prin acest panou.
- Toate comenzile artisan/composer rulează prin `docker compose exec app ...`, ca în subproiectul #1.

## Structura panoului & navigarea

O **resursă Filament per entitate** (nu resurse consolidate, nu pagini complet personalizate), grupate în meniul de navigare pe servicii:

- **📋 Ordinea de zi** — pagină custom, ecranul principal după autentificare (vezi secțiunea dedicată)
- **🅿️ Parcare** — Rezervări, Clienți, Loturi (cu zonele ca relation manager), Prețuri
- **🏨 Cazare** — Proprietăți, Camere, Rezervări
- **🚗 Rent-a-car** — Mașini, Clienți, Contracte, Mentenanță
- **💰 Buget** — Categorii, Tranzacții, Mesaje brute (Telegram), Salarii
- **⚙️ Sistem** — Plăți, Șabloane mesaje, Mesaje trimise, Jurnal automatizări (read-only), Utilizatori

Tabelele "copil" / dependente **nu** devin resurse separate — apar ca **Relation Manager** (tab-uri) în cadrul resursei părinte:
- pe o **Rezervare parcare**: tab "Imagini" (`parking_reservation_images`) și tab "Istoric status" (`parking_status_audits`, read-only)
- pe o **Mașină rent-a-car**: tab "Imagini" (`vehicle_images`)
- pe o **Proprietate de cazare**: tab "Legături sincronizare" (`lodging_sync_links` — Booking/Airbnb)
- pe o **Plată**: tab "Istoric modificări" (`payment_change_audits`, read-only)

Rezultat: ~18 resurse Filament + 1 pagină custom. Meniu mai lung, dar fiecare bucată rămâne mică, predictibilă și ușor de extins — consecvent cu modul în care a fost proiectată schema bazei de date (entități separate cu relații FK clare).

## Pagina "Ordinea de zi"

Pagină custom Filament, setată ca ecran principal după autentificare. Conține:

- **Selector de dată**: butoane rapide *Azi / Mâine / Poimâine* + selector de calendar pentru orice altă zi
- **Tab-uri pe servicii**: Parcare / Cazare / Rent-a-car
- Pentru fiecare tab, pentru ziua selectată:
  - **Listă cronologică de evenimente**: check-in/check-out parcare, sosiri/plecări cazare, preluări/predări contracte rent-a-car — fiecare rând arată ora, clientul/persoana și statusul; click pe un rând duce direct la rezervarea/contractul corespunzător (resursa Filament), pentru editare rapidă
  - **Rezumat de disponibilitate** sub formă de **bare de progres** (nu calendar tip grilă pe loc/cameră individuală — locurile de parcare se aranjează abia la sosirea mașinii, nu se rezervă în avans pe loc fix):
    - **Parcare**: bară totală + defalcare pe zone pentru Parcarea 1 (zonele A=11, B=8, C=14, D=12, E=9 → 54 locuri), bară separată pentru Parcarea 2 (30 locuri)
    - **Cazare**: ocupare camere per proprietate (Sky Center = 7 camere, Serafim = 5 camere)
    - **Rent-a-car**: mașini disponibile vs. în chirie/service

## Autentificare & roluri

Login standard Filament. Două roluri, deja prezente în coloana `users.role` (`admin` / `operator`, cu CHECK constraint din subproiectul #1):

- **Admin** — acces complet la tot, plus exclusiv:
  - gestionarea utilizatorilor (creare/editare/dezactivare conturi, schimbarea rolurilor) — grupul **Utilizatori**
  - secțiunea **💰 Buget** (Categorii, Tranzacții, Mesaje brute, Salarii) și resursa **Plăți** din grupul Sistem — date financiare sensibile
- **Operator** — acces la operațiunile zilnice: Ordinea de zi, Parcare, Cazare, Rent-a-car, Șabloane mesaje, Mesaje trimise, Jurnal automatizări — **fără** acces la Buget, Salarii, Plăți și fără gestionarea utilizatorilor

Notă: grupul de navigare **⚙️ Sistem** are vizibilitate mixtă — operatorul vede Șabloane mesaje / Mesaje trimise / Jurnal automatizări, dar nu și Plăți sau Utilizatori (acestea apar doar pentru admin). Filament permite ascunderea fiecărei resurse individual, indiferent de gruparea ei în navigare, deci grupul rămâne vizibil pentru ambele roluri cu un set diferit de resurse vizibile în interior.

Implementare: Laravel Policies pentru fiecare model + ascunderea grupurilor de navigare/resurselor în Filament pe baza `auth()->user()->role`.

## Domeniul de acoperire (scope)

CRUD complet pe toate entitățile de domeniu din schema (livrate în subproiectul #1):

| Grup | Resurse Filament | Note |
|---|---|---|
| **Parcare** | Rezervări, Clienți, Loturi (zonele ca relation manager), Prețuri | Imagini & istoric status ca relation manager pe Rezervări |
| **Cazare** | Proprietăți, Camere, Rezervări | `lodging_sync_links` ca relation manager pe Proprietăți |
| **Rent-a-car** | Mașini, Clienți, Contracte, Mentenanță | Imagini mașină ca relation manager pe Mașini |
| **Buget** | Categorii, Tranzacții, Mesaje brute (Telegram), Salarii | CRUD complet acum — botul Telegram din subproiectul #4 va popula aceleași tabele mai târziu, fără a necesita UI suplimentar |
| **Sistem** | Plăți, Șabloane mesaje, Mesaje trimise, Jurnal automatizări, Utilizatori | Audit-uri de plată ca relation manager pe Plăți; jurnalul de webhook/evenimente e doar pentru monitorizare/depanare (read-only, populat de viitoarea integrare n8n) |

**Ordinea de zi** — pagină dedicată, descrisă mai sus, ecranul principal al aplicației.

## În afara scopului (deferred to alte subproiecte)

- Integrarea live cu n8n / parsing email Gmail (subproiectul #3) — tabelele `automation_*` și `outbound_messages` rămân read-only / populate manual deocamdată
- Botul Telegram pentru parsing automat al tranzacțiilor de buget (subproiectul #4) — UI-ul de buget construit acum va fi reutilizat de bot, nu reconstruit
- Upload Excel pentru ture de lucru (subproiectul #5)
- Marketing — postări FB/IG/TikTok, reclame Google (subproiectul #6)
- Aplicația Android (subproiectul #7)

## Testare

Urmăm convenția stabilită în subproiectul #1: TDD pentru fiecare resursă/funcționalitate, teste Feature pentru CRUD (creare, editare, ștergere, restricții pe roluri) și pentru pagina "Ordinea de zi" (filtrare pe dată, calcul disponibilitate). Testele rulează prin `docker compose exec app php artisan test`.
