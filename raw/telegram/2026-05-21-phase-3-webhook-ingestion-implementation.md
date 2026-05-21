# Phase 3 — Webhook Ingestion Implementation

> Source: Project implementation (conversation + codebase)
> Collected: 2026-05-21
> Published: 2026-05-21

## Summary

Phase 3 replaces the placeholder `TelegramChatAutomationWebhookController` with full webhook ingestion. Incoming Telegram `Update` JSON is parsed; chats are upserted; messages are stored when `debug_enabled` or ingest-time dispute heuristics match; `ProcessTelegramChatMessageJob` is dispatched for Phase 4 processing.

## Components

- `TelegramChatWebhookIngestionService` / `TelegramChatWebhookIngestionServiceContract` — core ingestion logic; singleton in `AppServiceProvider`
- `TelegramChatAutomationWebhookController` — delegates to ingestion service, always returns `204 No Content`
- `ProcessTelegramChatMessageJob` — queued on `default`, `afterCommit()`; stub exits unless chat status is `active` (Phase 4 fills in processing)

## Ingestion flow

1. Require `update_id`; skip if `telegram_update_id` already exists in `telegram_chat_messages`
2. Require `message` key (non-message updates ignored)
3. Require `message.chat.id` and valid `message_id` (> 0)
4. DB transaction:
   - `firstOrCreate` `TelegramChat` by API chat id (new → `pending_moderation`, `standard_dispute`)
   - Update chat metadata (`type`, `title`, `username`, `raw_payload`, `last_message_at`); do not reset `status` on existing chats
   - Skip if composite unique `telegram_chat_id` + `telegram_message_id` already exists
   - Store message only when `debug_enabled` OR dispute-related heuristic
   - Dispatch `ProcessTelegramChatMessageJob`
5. Catch duplicate key `QueryException` (MySQL 1062, SQLite 19, PostgreSQL 23505) and return silently

## Dispute-related heuristic (ingest time)

Message is `is_dispute_related` when:

- `photo` array is non-empty, OR
- `document` object present, OR
- UUID v4 regex match in `text` or `caption`

Regex: `\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b`

## Message type mapping

- non-empty `photo` → `photo`
- `document` → `document`
- `text` string → `text`
- else → `unknown`

New rows use status `received`.

## Security

`VerifyTelegramChatAutomationSecretToken` middleware unchanged from Phase 2.
