# Telegram Chat Types and Trader Team Notifications — Phase 6–7 (Dispute Notification Jobs + Scheduling)

> Source: Cursor implementation session (p2p.cti)
> Collected: 2026-06-06
> Published: Unknown

## Summary

Phases 6–7 of the Trader Team Notifications feature: async dispute notification delivery to active `trader_team` chats via queue jobs, fault-isolated Telegram sends, and self-rescheduling reminder chain (15 minutes, then hourly while dispute remains `PENDING`). Phase 8 manual verification remains.

## Dispatch Trigger

`SendTelegramTraderTeamDisputeNotificationListener` (auto-discovered in `app/Listeners`) handles `DisputeOpenedEvent` and dispatches `SendTelegramTraderTeamDisputeNotificationJob` with `dispute_id`.

`DisputeOpenedEvent` already implements `ShouldDispatchAfterCommit`; jobs also call `afterCommit()` in constructor.

After deploy, run `php artisan event:clear` if cached events omit the new listener.

## Enum — `TelegramTraderTeamDisputeNotificationType`

File: `app/Enums/TelegramTraderTeamDisputeNotificationType.php`

Cases:

- `immediate`
- `fifteen_minute`
- `hourly`

Used for message template selection in the notification service and as reminder stage metadata in the reminder job.

## Service — `TelegramTraderTeamDisputeNotificationService`

File: `app/Services/Telegram/TelegramTraderTeamDisputeNotificationService.php`

Responsibilities:

- `findActiveTeamChatsForTrader(int $traderId)` — `chat_type = trader_team`, `status = active`, `whereHas` traders, eager-load matching trader pivot for mention
- `isDisputePending(Dispute)` — `DisputeStatus::PENDING`
- `sendNotifications(Dispute, type, TelegramChatBotServiceContract)` — per-chat send with isolated `TelegramChatBotException` / `Throwable` catch and structured `Log::warning`

Mention: pivot `telegram_username` rendered as `@{username} ` prefix when set.

Message texts match spec (immediate / 15-min / hourly) with order UUID.

## Job — `SendTelegramTraderTeamDisputeNotificationJob`

File: `app/Jobs/SendTelegramTraderTeamDisputeNotificationJob.php`

- Queue: `telegram-chat-automation`
- `afterCommit()` in constructor
- Loads dispute + order; returns if missing or not pending
- Sends `IMMEDIATE` notifications via service
- Dispatches `SendTelegramTraderTeamDisputeReminderJob` with `FIFTEEN_MINUTE_REMINDER` and `delay(now()->addMinutes(15))`

## Job — `SendTelegramTraderTeamDisputeReminderJob`

File: `app/Jobs/SendTelegramTraderTeamDisputeReminderJob.php`

- Queue: `telegram-chat-automation`
- `afterCommit()` in constructor
- Accepts `disputeId` + `TelegramTraderTeamDisputeNotificationType` reminder type (`fifteen_minute` or `hourly` only)
- Reloads dispute; returns if missing, not pending, or invalid reminder type
- Sends reminder notifications via service
- Self-schedules next `HOURLY_REMINDER` with `delay(now()->addHour())` after successful pending check

Scheduling strategy (Phase 7): no global scheduler scan; chain stops when dispute is no longer pending on reload.

## Listener

File: `app/Listeners/SendTelegramTraderTeamDisputeNotificationListener.php`

Synchronous listener (not `ShouldQueue`); only dispatches the immediate notification job.

## Files Added

- `app/Enums/TelegramTraderTeamDisputeNotificationType.php`
- `app/Services/Telegram/TelegramTraderTeamDisputeNotificationService.php`
- `app/Jobs/SendTelegramTraderTeamDisputeNotificationJob.php`
- `app/Jobs/SendTelegramTraderTeamDisputeReminderJob.php`
- `app/Listeners/SendTelegramTraderTeamDisputeNotificationListener.php`

## Not in Phase 6–7

- Phase 8 manual verification checklist
- Notification log table for idempotency (accepted at-least-once queue semantics for v1)
- Automated tests (deferred per project rule)
