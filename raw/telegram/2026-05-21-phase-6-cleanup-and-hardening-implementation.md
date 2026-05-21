# Phase 6 — Cleanup and Hardening Implementation

> Source: Project implementation (conversation + codebase)
> Collected: 2026-05-21
> Published: 2026-05-21

## Summary

Phase 6 completes the Telegram chat dispute automation feature: asynchronous cleanup of debug-only messages when debug mode is disabled, hardened private file deletion, and structured logging for processing failures.

## Cleanup job

- Class: `App\Jobs\CleanupTelegramChatDebugMessagesJob`
- Queue: `default`, `afterCommit()`, `tries = 3`, `timeout = 120`
- Dispatched from `Admin\TelegramChatController::toggleDebug` when `debug_enabled` transitions from `true` to `false`

### Behavior

1. Reload `TelegramChat`; exit if chat deleted.
2. If `debug_enabled` is still `true`, skip cleanup (user re-enabled debug before job ran) and log `info`.
3. Query messages: `telegram_chat_id` = chat id AND `is_dispute_related = false`.
4. Process in `chunkById(50)` with eager-loaded `attachments`.
5. For each message: delete attachment files via `TelegramChatFileService::deleteStoredFile()`, delete attachment rows, delete message row.
6. Log `info` with counts: `deleted_messages`, `deleted_attachments`, `deleted_files`.

## File deletion hardening

`TelegramChatFileService::deleteStoredFile()` now returns `bool` (file removed from disk).

- Allowed paths only under `telegram-chat-attachments/` prefix (no `..` in path).
- Invalid path: `Log::warning`, return `false` (DB row still deleted by job).
- Missing file on disk: return `false`.

Contract `TelegramChatFileServiceContract::deleteStoredFile()` updated to return `bool`.

## Admin UI

- `Index.vue` confirm modal when disabling debug: states that accumulated debug messages and files will be removed in the background.
- Flash on disable: «Режим отладки выключен. Запущена очистка накопленных debug-сообщений.»

## Logging (processing hardening)

- `StandardTelegramDisputeParser::markMessage()` — `Log::warning` when status is `failed` (includes optional exception class/message).
- `TelegramChatMessageProcessor` — `Log::warning` when no parser supports chat `parser_type`.
- `ProcessTelegramChatMessageJob` — `Log::error` on unexpected throwable, then rethrow for queue retry.

## Unchanged

- No new routes or migrations.
- Dispute-related messages (`is_dispute_related = true`) and their files are never deleted by cleanup.
