# Sky Center — Design: Bot Telegram Buget (Subproiect #4)

- **Data:** 2026-06-10
- **Autor:** brainstorming cu utilizatorul
- **Status:** aprobat
- **Subproiect:** #4 din roadmap — Bot Telegram pentru înregistrare buget

---

## 1. Context și scop

Sky Center înregistrează curent încasările și cheltuielile zilnice în **două grupuri Telegram**
(unul pentru încasări, unul pentru cheltuieli) prin mesaje text libere. Formatul variabil
(ex: `BX1234AB - 250c`, `Kaufland 77,92`, `C3, c5 - 600c (Adrianos)`) face parsarea automată
nesigură (~50% rata de succes). Scopul acestui subproiect este înlocuirea intrărilor libere cu
un **bot Telegram ghidat** (wizard cu butoane inline) care garantează date structurate la
100% și le salvează direct în `budget_transactions`.

Continuă pattern-ul „n8n subțire" din subproiectele #3 (flux 1 și 2): Laravel deține toată
logica, n8n este doar releu de transport.

---

## 2. Decizii de design confirmate

| Decizie | Valoare |
|---|---|
| Grupuri Telegram | 2 grupuri separate: încasări + cheltuieli |
| Boți Telegram | 2 boți separați (câte unul per grup), din restricția n8n: 1 trigger per bot |
| Gestionare stare | Tabel `telegram_sessions` în Laravel (per chat_id + user_id) |
| Expirare sesiune | 30 minute de inactivitate → sesiune ștearsă, wizard restartat |
| Monede acceptate | RON (implicit), EUR, USD |
| Cheltuieli | Întotdeauna RON |
| Plată EUR/USD | Întotdeauna cash (se presupune) |
| Stocare finală | `budget_raw_messages` (log brut) + `budget_transactions` (înregistrare) |
| Metoda de plată | Salvată în `budget_transactions.metadata->payment_method` |

---

## 3. Arhitectură

```
Grupul Telegram (Încasări)          Grupul Telegram (Cheltuieli)
Bot A (@skycenter_income_bot)       Bot B (@skycenter_expense_bot)
        │                                       │
        ▼                                       ▼
   n8n Workflow #1                        n8n Workflow #2
   Telegram Trigger (Bot A)              Telegram Trigger (Bot B)
   → Switch (message / callback_query)   → Switch (message / callback_query)
   → Answer Callback Query (dacă e cb)   → Answer Callback Query (dacă e cb)
   → HTTP POST la Laravel                → HTTP POST la Laravel
       /api/automation/telegram/income       /api/automation/telegram/expense
   → Parse răspuns {text, keyboard}      → Parse răspuns {text, keyboard}
   → Telegram Send / Edit Message        → Telegram Send / Edit Message
```

### Flux n8n (identic pentru ambele workflow-uri, ~5 noduri):

1. **Telegram Trigger** — ascultă `message` și `callback_query`
2. **Switch** — ramifică pe `body.callback_query` prezent/absent
3. **Telegram: Answer Callback Query** — oprește animația „loading" la butoane
4. **HTTP Request** — POST la Laravel cu payload-ul brut Telegram
5. **Telegram: Send Message / Edit Message** — trimite răspunsul returnat de Laravel

Laravel returnează întotdeauna:
```json
{
  "action": "send" | "edit" | "none",
  "chat_id": 123456789,
  "message_id": 42,
  "text": "...",
  "keyboard": { "inline_keyboard": [[...]] }
}
```

---

## 4. Wizard Grup Încasări

### 4.1 Trigger

Orice mesaj text SAU orice buton apăsat în grupul de încasări pornește/continuă wizard-ul.

### 4.2 Pașii wizard-ului

```
Pas 1 — Selectare serviciu
┌─────────────────────────────────────────────┐
│ 💰 Ce tip de încasare înregistrezi?         │
│ [🚗 Parcare]  [🏨 Hotel]  [🚙 Rent-a-car]  │
└─────────────────────────────────────────────┘

── dacă Parcare ──────────────────────────────

Pas 2P — Număr de înmatriculare
│ 🚗 Introdu numărul de înmatriculare:        │
│ (utilizatorul tastează, ex: BX 1234 AB)    │

Pas 3P — Sumă
│ 💵 Sumă încasată?                           │
│ (utilizatorul tastează, ex: 250 sau 75.50) │

Pas 4P — Metodă de plată
│ 💳 Metoda de plată:                         │
│ [Cash RON] [Card RON] [EUR cash]            │
│ [USD cash] [Transfer bancar]                │

── dacă Hotel ────────────────────────────────

Pas 2H — Selectare proprietate
│ 🏨 Proprietate:                             │
│ [Sky Center]  [Serafim]                     │

Pas 3H — Selectare cameră (multi-select)
│ 🛏 Camera(ele) — bifează și apasă ✅:       │
│ [Camera 1] [Camera 2] [Camera 3] [Camera 4]│
│ [Camera 5] [Camera 6] [Camera 7]            │  ← Sky Center
│ (sau Camera 1–5 pentru Serafim)            │
│ [✅ Confirmă selecția]                      │

Pas 4H — Sumă
│ 💵 Sumă totală pentru camerele selectate?  │

Pas 5H — Metodă de plată
│ [Cash RON] [Card RON] [EUR cash]            │
│ [USD cash] [Transfer bancar]                │

── dacă Rent-a-car ───────────────────────────

Pas 2R — Descriere (client / vehicul)
│ 🚙 Descriere scurtă (client / vehicul):    │
│ (utilizatorul tastează, ex: Seat Bogdan)   │

Pas 3R — Sumă
│ 💵 Sumă?                                   │

Pas 4R — Metodă de plată
│ [Cash RON] [Card RON] [EUR cash]            │
│ [USD cash] [Transfer bancar]                │

── final (toți) ──────────────────────────────

Confirmare
│ ✅ Salvat!                                  │
│ 🚗 Parcare BX1234AB — 250 RON (card)       │
│ Data: 10.06.2026 15:23                      │
└─────────────────────────────────────────────┘
```

### 4.3 Butoane multi-select (Hotel → camere)

Butoanele de cameră funcționează toggle: apăsarea unei camere o bifează `☑ Camera 3` sau
o debifează `Camera 3`. Starea selecției este păstrată în `telegram_sessions.data->rooms[]`.
Butonul `✅ Confirmă selecția` avansează la pasul următor.

---

## 5. Wizard Grup Cheltuieli

### 5.1 Trigger

Orice mesaj text SAU orice buton apăsat în grupul de cheltuieli pornește/continuă wizard-ul.

### 5.2 Pașii wizard-ului

```
Pas 1 — Categorie
┌──────────────────────────────────────────────────────┐
│ 📤 Selectează categoria cheltuielii:                 │
│ [🛒 Kaufland]    [🧹 Curățenie]   [🚗 Spălat mașini]│
│ [🔧 Instalatori] [🌿 Grădinar]   [📊 Contabil]      │
│ [⛽ Combustibil]  [🔩 Piese auto] [📋 Rovinietă]     │
│ [👤 Salarii]      [✏️ Altele...]                     │
└──────────────────────────────────────────────────────┘

── dacă Altele ────────────────────────────────────────

Pas 1b — Descriere custom
│ ✏️ Scrie descrierea cheltuielii:                     │
│ (utilizatorul tastează, ex: 700 uși, instalatori)   │

── toți ───────────────────────────────────────────────

Pas 2 — Sumă
│ 💵 Sumă (RON):                                      │
│ (utilizatorul tastează, ex: 300 sau 151.20)         │

Confirmare
│ ✅ Salvat!                                           │
│ 🧹 Curățenie — 300 RON                              │
│ Data: 10.06.2026 15:23                               │
└──────────────────────────────────────────────────────┘
```

---

## 6. Stare sesiune (`telegram_sessions`)

### Schema tabelului (migrare nouă)

```php
Schema::create('telegram_sessions', function (Blueprint $table): void {
    $table->id();
    $table->string('chat_id', 64)->index();
    $table->string('user_id', 64)->nullable();
    $table->string('username', 128)->nullable();
    $table->string('group_type', 16);          // 'income' | 'expense'
    $table->string('state', 64);               // pas curent
    $table->jsonb('data')->nullable();         // date colectate până acum
    $table->unsignedInteger('wizard_message_id')->nullable(); // msg_id editat
    $table->timestampTz('expires_at');         // now() + 30 min, resetat la fiecare pas
    $table->timestampsTz();

    $table->unique(['chat_id', 'user_id'], 'telegram_sessions_chat_user_unique');
});
```

### State machine — grup încasări

| Stare | Declanșat de | Pasul următor |
|---|---|---|
| `selecting_service` | orice mesaj/start | `waiting_plate` / `selecting_property` / `waiting_rent_desc` |
| `waiting_plate` | mesaj text (nr. înmatriculare) | `waiting_amount` |
| `selecting_property` | callback buton | `selecting_rooms` |
| `selecting_rooms` | callback toggle/confirm | `waiting_amount` (la confirm) |
| `waiting_rent_desc` | mesaj text | `waiting_amount` |
| `waiting_amount` | mesaj text (număr) | `selecting_payment` |
| `selecting_payment` | callback buton | DONE → salvează |

### State machine — grup cheltuieli

| Stare | Declanșat de | Pasul următor |
|---|---|---|
| `selecting_category` | orice mesaj/start | `waiting_custom_desc` / `waiting_expense_amount` |
| `waiting_custom_desc` | mesaj text | `waiting_expense_amount` |
| `waiting_expense_amount` | mesaj text (număr) | DONE → salvează |

---

## 7. Date salvate la finalizare wizard

### `budget_raw_messages`

```json
{
  "chat_id": "-100123456789",
  "message_id": "bot-session-<session_id>",
  "text": "[INCOME] Parcare BX1234AB - 250 RON card",
  "parsed": true,
  "received_at": "2026-06-10T15:23:00+03:00"
}
```

### `budget_transactions`

```json
{
  "type": "income",
  "service": "parking",
  "amount": 250.00,
  "currency": "RON",
  "occurred_on": "2026-06-10",
  "description": "Parcare BX1234AB",
  "telegram_chat": "income",
  "raw_message_id": 42,
  "metadata": {
    "payment_method": "card",
    "telegram_user": "SKY PARK",
    "wizard_data": { "plate": "BX1234AB" }
  }
}
```

Pentru cheltuieli:
```json
{
  "type": "expense",
  "category_id": 3,
  "service": "general",
  "amount": 300.00,
  "currency": "RON",
  "occurred_on": "2026-06-10",
  "description": "Curățenie",
  "telegram_chat": "expense",
  "metadata": {
    "telegram_user": "Copilasul Idealist"
  }
}
```

---

## 8. Endpoint-uri Laravel noi

Ambele endpoint-uri sunt protejate cu middleware `automation.token` existent.

### `POST /api/automation/telegram/income`

**Payload trimis de n8n:**
```json
{
  "update_type": "message" | "callback_query",
  "chat_id": "-100123456789",
  "user_id": "987654321",
  "username": "SKY PARK",
  "message_id": 101,
  "text": "250",
  "callback_query_id": "abc123",
  "callback_data": "payment:card"
}
```

**Răspuns Laravel:**
```json
{
  "action": "send" | "edit" | "none",
  "chat_id": "-100123456789",
  "message_id": 88,
  "text": "✅ Salvat!\n🚗 Parcare BX1234AB — 250 RON (card)",
  "keyboard": null
}
```

### `POST /api/automation/telegram/expense`

Identic structural cu `/income`, diferă logica internă.

---

## 9. Categorii de cheltuieli (seed extins)

Categorii adăugate în `BudgetCategorySeeder` (înlocuiesc setul minimal actual):

| Categorie | Serviciu | Emoji |
|---|---|---|
| Kaufland | general | 🛒 |
| Curățenie | general | 🧹 |
| Spălat mașini | general | 🚗 |
| Instalatori | general | 🔧 |
| Grădinar | general | 🌿 |
| Contabil | general | 📊 |
| Combustibil | general | ⛽ |
| Piese auto | general | 🔩 |
| Rovinietă | general | 📋 |
| Salarii | general | 👤 |
| Apă | hotel | 💧 |
| Lumină / electricitate | hotel | 💡 |
| Gaz | hotel | 🔥 |
| Inventar | hotel | 📦 |

Câmpul `emoji` este stocat în `budget_categories.metadata->emoji`.
Categoriile cu `kind='expense'` apar în wizard. Categoria specială „Altele" nu e în DB —
e o opțiune hardcodată care cere descriere liberă.

---

## 10. Validare input

| Input | Validare |
|---|---|
| Număr de înmatriculare | Orice text nevid, max 32 caractere, trim |
| Sumă | Regex `^\d{1,8}([.,]\d{1,2})?$`, virgula → punct, max 999999.99 |
| Descriere rent/custom | Orice text nevid, max 128 caractere |
| Dacă validarea eșuează | Bot trimite mesaj de eroare + același prompt (sesiunea rămâne pe același pas) |

---

## 11. Gestionarea erorilor și edge cases

| Situație | Comportament |
|---|---|
| Sesiune expirată (>30 min) | Wizard resetat, mesaj: „⏰ Sesiunea a expirat. Reîncepem:" |
| Utilizator tastează în mijlocul unui wizard al altcuiva | Fiecare user are propria sesiune per `(chat_id, user_id)` |
| Buton apăsat pe mesaj vechi | Callback procesat, sesiunea actuală ignoră mesajul vechi |
| Sumă invalidă | „❌ Sumă invalidă. Introdu un număr (ex: 250 sau 75.50):" |
| Hotel fără cameră selectată la Confirmă | „⚠️ Selectează cel puțin o cameră." |

---

## 12. Testare (PHPUnit)

### Wizard încasări
1. POST income `update_type=message` fără sesiune → răspuns `selecting_service` cu keyboard
2. POST income callback `service:parking` → stare `waiting_plate`, text corect
3. POST income text (nr. înmatriculare valid) → stare `waiting_amount`
4. POST income text (sumă validă `250`) → stare `selecting_payment`
5. POST income callback `payment:card` → sesiune ștearsă, `budget_transactions` creat, `budget_raw_messages` creat
6. POST income text (sumă invalidă `abc`) → eroare, stare neschimbată
7. POST income text callback `service:hotel` → `selecting_property`
8. POST income callback `property:skycenter` → `selecting_rooms` cu butoane Camera 1–7
9. Toggle cameră + Confirmă → `waiting_amount`
10. Flux complet rent-a-car → tranzacție salvată

### Wizard cheltuieli
11. POST expense fără sesiune → `selecting_category` cu keyboard
12. POST expense callback categorie standard → `waiting_expense_amount`
13. POST expense callback `category:custom` → `waiting_custom_desc`
14. POST expense text descriere → `waiting_expense_amount`
15. POST expense text sumă validă → sesiune ștearsă, `budget_transactions` creat (expense)
16. Sesiune expirată → wizard resetat

### Securitate / Token
17. Request fără token → `401`
18. Request cu token invalid → `401`

---

## 13. În afara scopului

- Rapoarte / sumarizare buget (subproiect ulterior)
- Editarea/ștergerea unei tranzacții din Telegram (se face din Filament)
- Notificări automate (ex: „Cheltuieli peste buget")
- Importul mesajelor vechi din istoricul Telegram
- Configurarea botului Telegram (@BotFather) — pas manual, documentat în README
- Workflow-urile n8n efective — configurate manual în n8n, în afara repo-ului

---

## 14. Pași de configurare manuală (non-cod)

Documentați în `docs/telegram-bot-setup.md` (creat la implementare):

1. Creează 2 boți prin `@BotFather`: `@skycenter_income_bot` + `@skycenter_expense_bot`
2. Copiază token-urile în `.env`: `TELEGRAM_INCOME_BOT_TOKEN` + `TELEGRAM_EXPENSE_BOT_TOKEN`
3. Adaugă boții în grupurile respective și acordă permisiunea de a trimite mesaje
4. Configurează webhook-ul n8n pentru fiecare bot
5. Importă cele 2 workflow-uri n8n (JSON-uri generate la implementare)
