# Telegram Chat Types and Trader Team Notifications Specification

> Sources: User conversation, 2026-06-06; Context7 docs for `irazasyed/telegram-bot-sdk`, 2026-06-06; existing Telegram chat automation code, 2026-06-06
> Raw: [Telegram Chat Types and Trader Team Dispute Notifications Requirements](../../raw/telegram/2026-06-06-telegram-chat-types-and-trader-team-dispute-notifications-requirements.md)
> Updated: 2026-06-06

## Overview

The Telegram chat automation module must evolve from a single-purpose dispute parser into an explicitly configured chat automation platform. New chats created from Telegram webhooks must be unassigned by default and must not run dispute automation until a Super Admin selects the chat function. The existing dispute chat behavior remains available as an explicit mode and must continue to behave exactly as it does today after selection. A new chat function, "Команда трейдеров", binds multiple trader users to Telegram chats and sends queued, fault-isolated dispute reminders to every team chat that contains the dispute trader.

## Current System Context

The project currently has two independent Telegram integrations:

- Existing user notification bot: `TelegramService`, `TelegramWebhookController`, `TelegramAccount`, `TELEGRAM_BOT_TOKEN`, and `irazasyed/telegram-bot-sdk`. This feature is unrelated and must not be changed.
- Chat automation bot: `TelegramChatBotService`, `TelegramChatWebhookIngestionService`, `TelegramChat`, `TelegramChatMessage`, `ProcessTelegramChatMessageJob`, and queue `telegram-chat-automation`. This is the integration to extend.

The current chat automation flow is:

1. Telegram posts to `POST /telegram/chat-automation/webhook`.
2. `VerifyTelegramChatAutomationSecretToken` verifies the webhook secret.
3. `TelegramChatWebhookIngestionService` resolves or creates `TelegramChat`.
4. It stores `TelegramChatMessage` when debug is enabled or message looks dispute-related.
5. It dispatches `ProcessTelegramChatMessageJob` on `telegram-chat-automation`.
6. The job processes only active chats.
7. `StandardTelegramDisputeParser` opens disputes only for `OrderStatus::FAIL` orders with a receipt attachment and no existing dispute.
8. Dispute resolution replies are sent by `SendTelegramDisputeResolutionNotificationJob`.

Important current default to change:

- `TelegramChatWebhookIngestionService::resolveTelegramChat()` creates new chats with `parser_type = standard_dispute`.
- This must become unassigned by default.

## Goals

- New Telegram chats are created as moderated but unassigned.
- Existing dispute-processing behavior becomes an explicitly selected chat function.
- Add "Команда трейдеров" chat function.
- Allow Super Admins to attach many trader users to a team chat.
- Allow one trader to belong to many team chats.
- Store an optional Telegram username/tag per trader per team chat.
- Send async dispute notifications to all team chats containing the dispute trader.
- Mention the trader when a Telegram tag is configured.
- Send immediate, 15-minute, and hourly reminders while the dispute remains open.
- Keep all Telegram delivery failures isolated from dispute creation and dispute lifecycle logic.
- Preserve the old user Telegram notification feature unchanged.

## Non-Goals

- Do not merge chat automation with the old user notification bot.
- Do not require Telegram account linking for trader team chat notifications.
- Do not add links to dispute pages in messages.
- Do not restrict notifications to only Telegram-created disputes.
- Do not introduce browser automation or synchronous Telegram sending in the dispute transaction.
- Do not change current dispute parser behavior once the chat is explicitly configured for dispute processing.

## Terminology

- Chat function: the high-level purpose selected for a Telegram chat.
- Unassigned chat: a discovered Telegram chat that has no function selected yet.
- Dispute-processing chat: current behavior using `StandardTelegramDisputeParser`.
- Trader team chat: "Команда трейдеров"; a Telegram chat mapped to one or more trader users for dispute notifications.

## Data Model

### TelegramChat

Replace the implicit dispute default with an explicit nullable function.

Recommended fields:

- Keep existing `status`, `debug_enabled`, Telegram metadata, and timestamps.
- Replace or complement `parser_type` with a nullable chat function column, for example `chat_type` or `function_type`.
- Allowed values:
  - `null` / unassigned
  - `dispute_processing`
  - `trader_team`
- `parser_type` can remain nullable for parser-backed modes.

Recommended compatibility approach:

- Make `parser_type` nullable.
- Add `chat_type` nullable enum/string.
- For dispute-processing chats: `chat_type = dispute_processing`, `parser_type = standard_dispute`.
- For trader team chats: `chat_type = trader_team`, `parser_type = null`.
- For new chats: `chat_type = null`, `parser_type = null`, `status = pending_moderation`.

This avoids overloading parser selection as the business meaning of the chat.

### Trader Team Membership

Add a pivot table dedicated to trader team chat membership.

Suggested table: `telegram_chat_traders`

Suggested columns:

- `id`
- `telegram_chat_id` foreign key to local `telegram_chats.id`
- `trader_id` foreign key to `users.id`
- `telegram_username` nullable string, stored normalized without leading `@`
- `created_at`
- `updated_at`

Suggested indexes:

- Unique: `telegram_chat_id + trader_id`
- Index: `trader_id`
- Index: `telegram_chat_id`

Validation:

- `telegram_chat_id` must reference a chat with `chat_type = trader_team` when adding memberships.
- `trader_id` must reference a user with Trader role.
- `telegram_username` is optional.
- Accept `@username` or `username`; store without `@`.
- Reject spaces and invalid Telegram username characters.

Model relationships:

- `TelegramChat::traders()` belongsToMany `User::class` with pivot `telegram_username`.
- `User::telegramTeamChats()` belongsToMany `TelegramChat::class`.

## Webhook Ingestion Changes

New chat creation:

1. Resolve Telegram chat by API chat id.
2. If missing, create:
   - `status = pending_moderation`
   - `chat_type = null`
   - `parser_type = null`
   - `debug_enabled = true` may remain as today for admin inspection.
3. Update chat metadata and `last_message_at`.
4. Store messages according to current debug/relevance rules, but do not process unassigned chats.

Processing gate:

- `ProcessTelegramChatMessageJob` must only call the parser processor when:
  - chat status is `active`
  - chat function is `dispute_processing`
  - parser type is `standard_dispute`
- If chat is unassigned or `trader_team`, message processing must stop safely.

When an admin changes an unassigned chat to dispute-processing and activates it:

- Existing `received` messages can be redispatched exactly like current activation behavior.
- The current parser must process them without behavior changes.

## Admin UI Changes

The existing page `resources/js/Pages/Admin/TelegramChats/Index.vue` should support a clear chat function selector.

Chat settings:

- Status: existing `pending_moderation`, `active`, `disabled`, `archived`.
- Chat function:
  - "Не назначен"
  - "Споры"
  - "Команда трейдеров"
- Parser type field should be hidden or derived for the "Споры" function.
- Debug toggle remains available as today.

For "Команда трейдеров":

- Show a team members block.
- Backend search input for traders.
- Add selected trader to chat.
- Optional Telegram username field per trader.
- Remove trader from chat.
- Edit Telegram username for existing membership.
- A trader may be added to multiple chats.

Search behavior:

- Search by trader email, because `email` is the default user display field in this project.
- Return only users with Trader role.
- Keep response small and paginated/limited.

## Routes and Controllers

Extend the Super Admin route group only.

Recommended endpoints:

- `GET admin.telegram-chats.trader-search`  
  Search traders for membership UI.
- `POST admin.telegram-chats.{telegramChat}.traders`  
  Add trader to a trader team chat.
- `PATCH admin.telegram-chats.{telegramChat}.traders.{trader}`  
  Update optional Telegram username.
- `DELETE admin.telegram-chats.{telegramChat}.traders.{trader}`  
  Remove trader from chat.

Alternative: create `Admin\TelegramChatTraderController`.

Requests:

- `StoreTelegramChatTraderRequest`
- `UpdateTelegramChatTraderRequest`
- `SearchTelegramChatTraderRequest` if needed

Authorization:

- Keep using `role:Super Admin` route middleware.
- Form Request `authorize()` can remain true if route middleware is authoritative.

## Dispute Notification Flow

Trigger point:

- Use the existing dispute creation flow, preferably around `DisputeOpenedEvent` or immediately after `DisputeService::create()` creates the dispute.
- The trigger must dispatch a job only. It must not send Telegram messages synchronously.

Recommended jobs:

- `SendTelegramTraderTeamDisputeNotificationJob`
- `SendTelegramTraderTeamDisputeReminderJob`

Queue:

- Use existing `telegram-chat-automation`.
- Use `afterCommit()`.

Immediate notification:

1. A dispute is created.
2. Dispatch immediate notification job after commit.
3. Job loads dispute with order and trader.
4. Job finds all active trader team chats containing `dispute.trader_id`.
5. For every chat, send a message through `TelegramChatBotService::sendChatMessage()`.
6. If membership has `telegram_username`, include `@username` in the text.
7. Log failures per chat; do not throw failures into dispute flow.
8. Dispatch or schedule the 15-minute reminder.

Reminder sequence:

1. Immediate notification job schedules first reminder with 15-minute delay.
2. Reminder job checks the dispute fresh from DB.
3. If dispute is no longer open/pending, stop.
4. If still open:
   - send reminder to all current matching active trader team chats
   - schedule next reminder with 60-minute delay
5. Repeat hourly while dispute remains open.

Open status:

- Treat `DisputeStatus::PENDING` as open unless the domain already has a better "open" predicate.
- Stop on `ACCEPTED`, `CANCELED`, rollback states that are not pending, or missing dispute.

## Message Texts

Keep content short, professional, and without links.

Immediate:

```text
{mention}Открыт новый спор.
UUID сделки: {uuid}
Пожалуйста, обработайте спор.
```

15-minute reminder:

```text
{mention}Спор всё ещё ожидает обработки.
UUID сделки: {uuid}
Пожалуйста, обработайте его как можно скорее.
```

Hourly reminder:

```text
{mention}Напоминание: спор всё ещё открыт.
UUID сделки: {uuid}
Требуется обработка спора.
```

Mention formatting:

- Store `telegram_username` without `@`.
- Render as `@{telegram_username} ` when non-empty.
- Use plain text. No parse mode is required for username mentions.

## Fault Isolation and Reliability

Core rule:

- Telegram team notifications must never break dispute creation or dispute resolution.

Implementation rules:

- All Telegram sends happen in queue jobs.
- Jobs use `afterCommit()`.
- Jobs reload dispute/chat state from DB.
- If the dispute is missing or no longer open, jobs return.
- If no chats are found, jobs return.
- Catch `TelegramChatBotException` and `Throwable` around each send or around each chat iteration.
- Log enough context:
  - `dispute_id`
  - `order_id`
  - `order_uuid`
  - `trader_id`
  - `telegram_chat_id` local id
  - Telegram API chat id
  - error message and exception class
- Do not rollback dispute creation.
- Do not affect the old `TelegramService` / `TelegramAccount` notification flow.

Idempotency recommendation:

- For v1, delayed reminder jobs can be self-scheduling and state-checked.
- To avoid accidental duplicate immediate messages from job retries, consider a notification log table before implementation if duplicate sends are unacceptable.
- If no log table is added, accept at-least-once queue semantics and keep messages idempotent in wording.

## Interaction With Existing Dispute Chat Automation

Existing dispute chat mode must preserve:

- Receipt + UUID detection.
- `FAIL`-only order status gate.
- Duplicate dispute handling.
- Immediate replies to Telegram source messages.
- Resolution notifications replying to source Telegram messages.
- Debug attachment behavior.
- Queue `telegram-chat-automation`.

The new trader team notification flow is separate:

- It does not parse incoming Telegram messages.
- It does not create disputes.
- It does not reply to source dispute chat messages.
- It only sends outbound notifications to configured trader team chats.

## Implementation Plan

### Phase 1: Domain Enums and Schema

1. Add a chat function enum, for example `TelegramChatType`.
2. Add nullable `chat_type` to `telegram_chats`.
3. Make `parser_type` nullable if it is not already.
4. Backfill existing dispute-processing chats:
   - chats with `parser_type = standard_dispute` become `chat_type = dispute_processing`
5. Ensure new chats default to `chat_type = null`, `parser_type = null`.
6. Add `telegram_chat_traders` pivot table.

### Phase 2: Models and Resources

1. Update `TelegramChat` casts and fillable fields.
2. Add trader membership relationships.
3. Add a resource shape for team traders in `TelegramChatResource`.
4. Ensure API resources still call `->resolve()` where needed for non-paginated props.

### Phase 3: Webhook and Processing Gates

1. Change `TelegramChatWebhookIngestionService::resolveTelegramChat()` default creation values.
2. Update `ProcessTelegramChatMessageJob` or `TelegramChatMessageProcessor` so unassigned/team chats do not run dispute parsers.
3. Preserve redispatch behavior when a chat becomes active and dispute-processing.
4. Verify existing dispute-processing chats still process messages after explicit mode selection.

### Phase 4: Admin Backend

1. Extend `TelegramChatController::update()` validation to accept chat function.
2. Derive parser settings:
   - `dispute_processing` => `parser_type = standard_dispute`
   - `trader_team` or unassigned => `parser_type = null`
3. Add trader search endpoint.
4. Add membership add/update/delete endpoints.
5. Enforce Trader role and unique membership.
6. Normalize Telegram username.

### Phase 5: Admin Frontend

1. Update `Admin/TelegramChats/Index.vue` chat settings panel.
2. Add "Не назначен", "Споры", "Команда трейдеров" selector.
3. Hide parser implementation details from UI when possible.
4. Add trader team membership UI only for `trader_team`.
5. Implement backend-powered trader search.
6. Add optional Telegram username input.
7. Use existing DaisyUI/Tailwind style and `ConfirmModal` where removal needs confirmation.

### Phase 6: Dispute Notification Jobs

1. Add immediate notification job.
2. Add reminder job or a single job with reminder stage metadata.
3. Dispatch from `DisputeOpenedEvent` listener or after `DisputeService::create()`.
4. Use queue `telegram-chat-automation`.
5. Use `afterCommit()`.
6. Load matching active trader team chats at send time.
7. Send to all chats.
8. Catch and log Telegram failures.
9. Schedule 15-minute reminder.
10. Schedule hourly reminders while dispute remains pending.

### Phase 7: Scheduling Strategy

Preferred: self-rescheduling delayed job.

- Immediate job dispatches first reminder with `delay(now()->addMinutes(15))`.
- Reminder job schedules another copy with `delay(now()->addHour())` only after it confirms the dispute remains pending.

This avoids a global scheduler scan and naturally stops when a dispute is closed.

### Phase 8: Verification

Manual verification checklist:

1. New Telegram chat appears as pending and unassigned.
2. Incoming messages in unassigned chat do not open disputes.
3. Selecting "Споры" restores current dispute parser behavior.
4. Selecting "Команда трейдеров" allows adding multiple traders.
5. One trader can be attached to multiple chats.
6. New dispute sends message to every active team chat containing trader.
7. Username mention appears when configured.
8. No message is sent when trader has no team chat.
9. 15-minute reminder sends only if dispute is still pending.
10. Hourly reminder repeats only while pending.
11. Accepting/canceling dispute stops later reminders.
12. Simulated Telegram failure is logged and does not break dispute creation.
13. Old Telegram user notification bot still works as before.

Automated tests should be added only when explicitly requested, following project rule. If tests are requested later, cover domain gating, membership queries, queue dispatch, and reminder stop conditions.

## Risks and Decisions

- Using `parser_type` as the only chat function would make trader team chats awkward. Add a separate nullable chat function.
- Queue retries can create duplicate Telegram messages. Decide before implementation whether to add a notification log table. If not, accept at-least-once delivery.
- Existing chats must be backfilled carefully so shipped dispute automation does not silently stop.
- Reminder jobs must check current DB state, not trust serialized dispute status.
- Telegram username mentions by `@username` only work if the username is valid and public; failure to notify the user personally should not block chat-level notification.

## See Also

- [Telegram Chat Dispute Automation Plan](telegram-chat-dispute-automation-plan.md)
- [Telegram Dispute Reply and Resolution Notifications Specification](telegram-dispute-reply-and-resolution-notifications-spec.md)
