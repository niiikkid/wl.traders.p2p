# Telegram Chat Types and Trader Team Notifications — Phase 3 (Webhook and Processing Gates)

> Source: Cursor implementation session (p2p.cti)
> Collected: 2026-06-06
> Published: Unknown

## Summary

Phase 3 of the Trader Team Notifications feature: webhook creates unassigned chats by default; dispute parser runs only for explicitly configured active dispute-processing chats. Debug attachment storage unchanged for all chats. No admin backend/UI or notification jobs yet.

## Webhook Ingestion

### `TelegramChatWebhookIngestionService::resolveTelegramChat()`

`firstOrCreate` defaults for new chats:

- `status` → `pending_moderation`
- `chat_type` → `null`
- `parser_type` → `null` (was `standard_dispute` before Phase 3)
- `debug_enabled` → `true`

Removed import of `TelegramChatParserType` from the ingestion service.

Message storage and `ProcessTelegramChatMessageJob` dispatch behavior unchanged: messages still stored when `debug_enabled` or dispute-related; job still dispatched after create.

## Processing Gate

### `TelegramChat::canProcessDisputeMessages(): bool` (new)

Returns `true` only when all conditions hold:

1. `status` is `active`
2. `chat_type` is not `trader_team`
3. If `chat_type` is set, it must be `dispute_processing`
4. `parser_type` is `standard_dispute`

Transitional rule for pre-phase-4 admin UI: `chat_type = null` with `parser_type = standard_dispute` still allows processing (current admin only sets `parser_type` on update).

Returns `false` for:

- unassigned chats (`chat_type` null, `parser_type` null)
- `trader_team` chats
- non-active chats

### `ProcessTelegramChatMessageJob`

- `storeDebugAttachmentsIfNeeded()` runs before the gate (all chats, including pending/unassigned)
- Parser `process()` called only when `canProcessDisputeMessages()` is true
- Replaced direct `TelegramChatStatus::ACTIVE` check with `canProcessDisputeMessages()`

### `TelegramChatMessageProcessor::process()`

- Early return when `! $telegramChat->canProcessDisputeMessages()`
- Skip log includes nullable `chat_type` and `parser_type` values

## Redispatch

`TelegramChat::redispatchReceivedMessages()` unchanged. `TelegramChatController::update()` still redispatches on transition to `active`. Jobs no-op safely until chat is configured for dispute processing.

## Files Changed

- `app/Services/Telegram/TelegramChatWebhookIngestionService.php`
- `app/Models/TelegramChat.php`
- `app/Jobs/ProcessTelegramChatMessageJob.php`
- `app/Services/Telegram/TelegramChatMessageProcessor.php`

## Behavior Matrix

| Chat state | Debug attachments | Dispute parser |
|------------|-------------------|----------------|
| New unassigned (`null`/`null`, pending) | yes (if debug or dispute-related) | no |
| `trader_team` | yes | no |
| `dispute_processing` + `standard_dispute` + active | yes | yes |
| Backfilled existing dispute chats | yes | yes |
| Admin sets only `parser_type=standard_dispute` (transitional) | yes | yes when active |

## Not in Phase 3

- Admin `chat_type` selector and membership CRUD (Phase 4–5)
- Dispute team notification jobs (Phase 6–7)
- Automated tests (deferred per project rule)
