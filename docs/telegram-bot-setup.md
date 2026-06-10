# Telegram Bot Setup Guide

## Overview

Two separate Telegram bots are required — one per group:
- **Bot A** (`@skycenter_income_bot`) — grupul de încasări
- **Bot B** (`@skycenter_expense_bot`) — grupul de cheltuieli

## Step 1: Create the bots via @BotFather

1. Open Telegram and message `@BotFather`
2. Run `/newbot` for each bot and follow prompts
3. Copy the API token for each bot

## Step 2: Configure .env

Add to `.env`:
```
TELEGRAM_INCOME_BOT_TOKEN=<token_for_bot_A>
TELEGRAM_EXPENSE_BOT_TOKEN=<token_for_bot_B>
```

## Step 3: Add bots to groups

1. Add each bot to its respective Telegram group
2. Grant the bot permission to send messages and read messages (disable privacy mode via @BotFather → `/setprivacy` → Disable, for group message reading)

## Step 4: Configure n8n workflows

Two n8n workflows required (one per bot). Each workflow has ~5 nodes:

### Node 1 — Telegram Trigger
- Credential: Bot token
- Updates to receive: `message`, `callback_query`

### Node 2 — Switch
- Condition A: `{{ $json.body.callback_query }}` is not empty → route to Node 3 (callback path)
- Condition B: default → skip Node 3

### Node 3 — Telegram: Answer Callback Query (callback path only)
- Operation: Answer Callback Query
- Callback Query ID: `{{ $json.body.callback_query.id }}`

### Node 4 — HTTP Request (POST to Laravel)
- Method: POST
- URL: `http://localhost/api/automation/telegram/income` (or `/expense`)
- Headers: `Authorization: Bearer <AUTOMATION_API_TOKEN>`
- Body (JSON):
```json
{
  "update_type": "{{ $json.body.callback_query ? 'callback_query' : 'message' }}",
  "chat_id": "{{ $json.body.message.chat.id ?? $json.body.callback_query.message.chat.id }}",
  "user_id": "{{ $json.body.message.from.id ?? $json.body.callback_query.from.id }}",
  "username": "{{ $json.body.message.from.username ?? $json.body.callback_query.from.username }}",
  "message_id": "{{ $json.body.message.message_id ?? $json.body.callback_query.message.message_id }}",
  "text": "{{ $json.body.message.text ?? '' }}",
  "callback_query_id": "{{ $json.body.callback_query.id ?? '' }}",
  "callback_data": "{{ $json.body.callback_query.data ?? '' }}"
}
```

### Node 5 — Telegram: Send Message
- Operation: Send Message
- Chat ID: `{{ $json.chat_id }}`
- Text: `{{ $json.text }}`
- Reply Markup (if `$json.keyboard` is not null): paste `{{ JSON.stringify($json.keyboard) }}`
- Parse Mode: HTML (optional)

## Step 5: Activate workflows

Activate both workflows in n8n. Test by sending a message to each group.
