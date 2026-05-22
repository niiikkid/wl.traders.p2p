# Telegram Dispute Resolution Notifications Implementation (Feature 2)

> Source: Cursor agent implementation session, 2026-05-22
> Collected: 2026-05-22
> Published: Unknown

Implemented Feature 2 from the Telegram dispute reply and resolution notifications specification: asynchronous Telegram replies after dispute accept or reject for disputes opened from Telegram chat automation.

## Scope delivered

- Phase 1 (complete): `TelegramChatBotServiceContract::sendChatDocument()` and `TelegramChatBotService::sendChatDocument()` — multipart `sendDocument` with optional `caption` and `reply_parameters` (JSON-encoded for multipart).
- Phase 3 (complete): `SendTelegramDisputeResolutionNotificationJob` on queue `telegram-chat-automation` with `afterCommit()`.
- Phase 4 (complete): job dispatched from `DisputeService::accept()` and `DisputeService::cancel()` after successful status update inside `Transaction::run()`.
- Not in this change: PHPUnit tests; idempotency columns on `telegram_chat_messages`; manual Phase 5 verification in a live Telegram chat.

## Files changed

- `app/Contracts/TelegramChatBotServiceContract.php` — `sendChatDocument()`
- `app/Services/Telegram/TelegramChatBotService.php` — `sendChatDocument()` implementation
- `app/Jobs/SendTelegramDisputeResolutionNotificationJob.php` — new job
- `app/Services/Dispute/DisputeService.php` — dispatch after accept/cancel

## Job behavior

- Loads `Dispute` with `order`; exits if missing or status does not match constructor argument.
- Finds `TelegramChatMessage` by `dispute_id` with `telegramChat`; exits if no Telegram source (manual disputes, non-Telegram flows).
- **Accepted:** `sendChatMessage` with `Спор принят.\nUUID сделки: <uuid>` as reply to source `telegram_message_id`.
- **Canceled:** tries `sendChatDocument` with bank statement from `storage/dispute-bank-statements/<filename>` and caption `Спор отклонён.\nUUID сделки: <uuid>`; on missing/unreadable file or Telegram error, falls back to text including `Не удалось загрузить выписку.`
- Bank statement path validated with `realpath` confined to `dispute-bank-statements` directory.
- `TelegramChatBotException` and other throwables caught and logged; dispute state never changed by notification failures.
- `rollback()` does not dispatch; no notification for pending-only flows.

## Dispatch

- `SendTelegramDisputeResolutionNotificationJob::dispatch($dispute->id, DisputeStatus::ACCEPTED)` after accept update.
- `SendTelegramDisputeResolutionNotificationJob::dispatch($dispute->id, DisputeStatus::CANCELED)` after cancel update (after bank statement persisted).

## Manual verification (pending)

- Accept Telegram-originated dispute → reply `Спор принят.` on original message.
- Reject with valid bank statement → single `sendDocument` reply with caption.
- Missing statement file → text fallback with `Не удалось загрузить выписку.`
- Accept/reject manual dispute → no Telegram message.
- Rollback → no Telegram message.
