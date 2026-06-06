# Telegram Chat Types and Trader Team Notifications — Phase 1 (Domain Enums and Schema)

> Source: Cursor implementation session (p2p.cti)
> Collected: 2026-06-06
> Published: Unknown

## Summary

Phase 1 of the Trader Team Notifications feature: domain enum, `telegram_chats` schema changes, backfill for existing dispute-processing chats, and `telegram_chat_traders` pivot table. No webhook gating, admin UI, API endpoints, relationships, resources, or notification jobs yet.

## Enum

### `app/Enums/TelegramChatType.php`

- `DISPUTE_PROCESSING = 'dispute_processing'`
- `TRADER_TEAM = 'trader_team'`
- Uses `Enumable` trait like other project enums.
- Unassigned chats use `chat_type = null` (no enum case).

## Migrations

### `2026_06_06_074023_add_chat_type_to_telegram_chats_table.php`

1. Adds nullable `chat_type` string column after `status` with index.
2. Backfills existing rows: `parser_type = standard_dispute` → `chat_type = dispute_processing`.
3. Changes `parser_type` to nullable with `default(null)` (removes previous `standard_dispute` default).

Down migration restores `parser_type` default `standard_dispute` and drops `chat_type`.

### `2026_06_06_074023_create_telegram_chat_traders_table.php`

Creates `telegram_chat_traders`:

- `id`
- `telegram_chat_id` FK → `telegram_chats`, cascade on delete
- `trader_id` FK → `users`, cascade on delete
- `telegram_username` nullable string
- `timestamps`
- unique `(telegram_chat_id, trader_id)`
- indexes on `trader_id` and `telegram_chat_id`

## Model (partial, ahead of Phase 2)

### `TelegramChat` (updated)

- `$fillable` includes `chat_type`
- Casts: `chat_type` → `TelegramChatType::class`, `parser_type` now nullable in PHPDoc
- Relationships for trader membership not added yet (Phase 2)

## Verification

Migration applied successfully. Sample existing chat after backfill: `chat_type=dispute_processing`, `parser_type=standard_dispute`.

## Not in Phase 1

- `TelegramChat::traders()` / `User::telegramTeamChats()` relationships
- `TelegramChatResource` team trader shape
- Webhook default change (`chat_type=null`, `parser_type=null` on new chats) — Phase 3
- `ProcessTelegramChatMessageJob` / parser gating by `chat_type` — Phase 3
- Admin backend and frontend — Phases 4–5
- Dispute notification jobs and reminders — Phases 6–7

`TelegramChatWebhookIngestionService::resolveTelegramChat()` still creates new chats with `parser_type = standard_dispute` until Phase 3.
