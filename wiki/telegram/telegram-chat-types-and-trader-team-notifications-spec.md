# Telegram Chat Types and Trader Team Notifications Specification

> Sources: User conversation, 2026-06-06; Context7 docs for `irazasyed/telegram-bot-sdk`, 2026-06-06; existing Telegram chat automation code, 2026-06-06; implementation session, 2026-06-06; Phase 3 webhook and processing gates, 2026-06-06; Phase 4 admin backend, 2026-06-06; Phase 5 admin frontend, 2026-06-06; Phase 6–7 dispute notification jobs and scheduling, 2026-06-06
> Raw: [Telegram Chat Types and Trader Team Dispute Notifications Requirements](../../raw/telegram/2026-06-06-telegram-chat-types-and-trader-team-dispute-notifications-requirements.md); [Phase 1 Domain Enums and Schema](../../raw/telegram/2026-06-06-phase-1-domain-enums-and-schema.md); [Phase 2 Models and Resources](../../raw/telegram/2026-06-06-phase-2-models-and-resources.md); [Phase 3 Webhook and Processing Gates](../../raw/telegram/2026-06-06-phase-3-webhook-and-processing-gates.md); [Phase 4 Admin Backend](../../raw/telegram/2026-06-06-phase-4-admin-backend.md); [Phase 5 Admin Frontend](../../raw/telegram/2026-06-06-phase-5-admin-frontend.md); [Phase 6–7 Dispute Notification Jobs](../../raw/telegram/2026-06-06-phase-6-dispute-notification-jobs.md)
> Updated: 2026-06-06

## Implementation Status

| Phase | Status | Notes |
|-------|--------|-------|
| 1. Domain enums and schema | **Done** | `TelegramChatType`, `chat_type` column, nullable `parser_type`, backfill, `telegram_chat_traders`; `TelegramChat` casts/fillable |
| 2. Models and resources | **Done** | `TelegramChat::traders()`, `User::telegramTeamChats()`, `TelegramChatTraderResource`, `chat_type`/`team_traders` in `TelegramChatResource`; selected chat eager loads traders |
| 3. Webhook and processing gates | **Done** | Unassigned defaults in webhook; `TelegramChat::canProcessDisputeMessages()` gates job and processor |
| 4. Admin backend | **Done** | `chat_type` update + derived `parser_type`; `TelegramChatTraderController`; trader search; membership CRUD; `TelegramUsernameNormalizer`; `chatTypes` Inertia prop |
| 5. Admin frontend | **Done** | `Index.vue` chat function selector; team membership UI; backend trader search; username edit; `ConfirmModal` on remove; `parserTypes` prop removed |
| 6. Dispute notification jobs | **Done** | `SendTelegramTraderTeamDisputeNotificationJob`, `SendTelegramTraderTeamDisputeReminderJob`, `TelegramTraderTeamDisputeNotificationService`, `SendTelegramTraderTeamDisputeNotificationListener` on `DisputeOpenedEvent` |
| 7. Scheduling strategy | **Done** | Self-rescheduling delayed jobs: immediate → +15 min → +1 hour loop while `PENDING` |
| 8. Verification | Pending | Manual checklist; automated tests deferred |

Feature is **not shipped** end-to-end until Phase 8 manual verification passes. Phases 1–7 cover schema, admin UI, processing gates, and async notification delivery with reminders.

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

**Shipped in Phase 3:** new webhook chats are unassigned by default (`chat_type = null`, `parser_type = null`). Dispute parsing is gated by `TelegramChat::canProcessDisputeMessages()`.

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

Model relationships (**shipped in Phase 2**):

- `TelegramChat::traders()` — `belongsToMany(User::class, 'telegram_chat_traders', ...)` with pivot `telegram_username` and timestamps.
- `User::telegramTeamChats()` — inverse `belongsToMany(TelegramChat::class, 'telegram_chat_traders', ...)`.

### API Resource Shape (Phase 2)

`TelegramChatTraderResource` — one row per membership:

| Field | Source |
|-------|--------|
| `id` | Trader `users.id` |
| `email` | Trader email (UI display field) |
| `telegram_username` | Pivot, nullable |
| `telegram_tag` | `@username` when set |
| `created_at`, `updated_at` | Pivot timestamps |

`TelegramChatResource` additions:

- `chat_type` — nullable
- `parser_type` — nullable-safe
- `team_traders` — only when `traders` is eager loaded; nested collection resolved for Inertia `selectedChat`

`TelegramChatController::index()` loads `traders` ordered by `users.email` for the selected chat only.

## Webhook Ingestion Changes

**Implemented in Phase 3.**

New chat creation (`TelegramChatWebhookIngestionService::resolveTelegramChat()`):

1. Resolve Telegram chat by API chat id.
2. If missing, create:
   - `status = pending_moderation`
   - `chat_type = null`
   - `parser_type = null`
   - `debug_enabled = true`
3. Update chat metadata and `last_message_at`.
4. Store messages according to current debug/relevance rules when `debug_enabled` or dispute-related.
5. Dispatch `ProcessTelegramChatMessageJob` as before; parser no-ops for unassigned chats.

Processing gate (`TelegramChat::canProcessDisputeMessages()`):

- Returns `true` only when chat is `active`, not `trader_team`, `parser_type = standard_dispute`, and `chat_type` is `null` or `dispute_processing`.
- Transitional: pre-phase-4 admin UI may set only `parser_type`; `chat_type = null` + `parser_type = standard_dispute` still processes when active.
- Used in `ProcessTelegramChatMessageJob` (before `process()`) and `TelegramChatMessageProcessor::process()`.
- `storeDebugAttachmentsIfNeeded()` runs for all chats regardless of gate.

| Chat state | Debug attachments | Dispute parser |
|------------|-------------------|----------------|
| Unassigned (`null`/`null`) | yes (if debug or dispute-related) | no |
| `trader_team` | yes | no |
| `dispute_processing` + active | yes | yes |
| Backfilled existing chats | yes | yes |

When an admin activates a dispute-processing chat:

- `redispatchReceivedMessages()` unchanged; jobs no-op until chat passes `canProcessDisputeMessages()`.
- Once configured and active, existing parser behavior is unchanged.

## Admin UI Changes

**Shipped in Phase 5.** Page: `resources/js/Pages/Admin/TelegramChats/Index.vue`.

### Chat settings panel

- Status select unchanged (`chatStatuses`).
- Chat function select (`chatTypes` Inertia prop): «Не назначен», «Споры», «Команда трейдеров».
- Form saves `chat_type` (not `parser_type`); parser derived on backend.
- Parser implementation hidden; header badge shows function label.
- Debug toggle unchanged; `ConfirmModal` when disabling debug.

### Trader team block (when `chat_type = trader_team`)

- Warning until chat is saved with `trader_team` function.
- Members table from `selectedChat.team_traders`: email, editable `telegram_username`, save, remove.
- Add trader: debounced email search (`admin.telegram-chats.trader-search`), optional username, excludes existing members.
- Remove via `ConfirmModal`.
- Mutations use Phase 4 routes (`traders.store`, `traders.update`, `traders.destroy`).

Search behavior (backend, Phase 4):

- Search by trader `email`; Trader role only; limit 20.
- Response: `{ traders: [{ id, email }, ...] }`.

## Routes and Controllers

**Shipped in Phase 4.** Super Admin route group only; authorization via existing `role:Super Admin` middleware.

### Chat update

`TelegramChatController::update()` — accepts `chat_type` (nullable) and transitional `parser_type`; derives parser via `resolveChatConfiguration()`:

- `dispute_processing` → `parser_type = standard_dispute`
- `trader_team` or unassigned → `parser_type = null`

Legacy `parser_type`-only path in `resolveChatConfiguration()` remains for transitional compatibility; Phase 5 frontend sends `chat_type` only.

### Trader membership — `TelegramChatTraderController`

| Method | Route name | Action |
|--------|------------|--------|
| GET | `admin.telegram-chats.trader-search` | Search traders by email (limit 20, Trader role) |
| POST | `admin.telegram-chats.traders.store` | Add trader to team chat |
| PATCH | `admin.telegram-chats.traders.update` | Update pivot `telegram_username` |
| DELETE | `admin.telegram-chats.traders.destroy` | Remove trader from chat |

Form Requests: `SearchTraderRequest`, `StoreTraderRequest`, `UpdateTraderRequest`.

Search response: `{ traders: [{ id, email }, ...] }`.

Membership mutations return `RedirectResponse` with flash message. Store/update validate `chat_type = trader_team`, Trader role, unique membership; username normalized via `TelegramUsernameNormalizer`.

`trader-search` route registered before `{telegramChat}` parameterized routes.

### Authorization

Form Request `authorize()` returns true; route middleware is authoritative.

## Dispute Notification Flow

**Shipped in Phases 6–7.**

Trigger:

- `DisputeService::create()` dispatches `DisputeOpenedEvent` (after commit).
- `SendTelegramTraderTeamDisputeNotificationListener` dispatches `SendTelegramTraderTeamDisputeNotificationJob` (sync listener; no Telegram I/O in listener).

Jobs:

- `SendTelegramTraderTeamDisputeNotificationJob` — immediate notification
- `SendTelegramTraderTeamDisputeReminderJob` — 15-minute and hourly reminders

Shared service: `TelegramTraderTeamDisputeNotificationService`.

Enum: `TelegramTraderTeamDisputeNotificationType` (`immediate`, `fifteen_minute`, `hourly`).

Queue: `telegram-chat-automation`; both jobs use `afterCommit()`.

Immediate notification:

1. Dispute created → `DisputeOpenedEvent` → listener dispatches immediate job.
2. Job reloads dispute with order; returns if missing or not `PENDING`.
3. Service finds active `trader_team` chats for `dispute.trader_id`.
4. Per chat: `TelegramChatBotService::sendChatMessage()` with optional `@username` mention from pivot.
5. Failures logged per chat (`TelegramChatBotException` / `Throwable`); dispute flow unaffected.
6. Job schedules `SendTelegramTraderTeamDisputeReminderJob` (`fifteen_minute`) with `delay(now()->addMinutes(15))`.

Reminder sequence:

1. Reminder job reloads dispute; returns if missing or not `PENDING`.
2. Sends reminder to all current matching active team chats.
3. Self-dispatches `HOURLY_REMINDER` with `delay(now()->addHour())`.
4. Hourly job repeats step 1–3 until dispute closes.

Post-deploy: run `php artisan event:clear` if cached events omit the new listener.

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

### Phase 1: Domain Enums and Schema — **Done**

Shipped in code (2026-06-06):

1. **`TelegramChatType` enum** — `app/Enums/TelegramChatType.php` with `dispute_processing`, `trader_team`; unassigned = `null`.
2. **Nullable `chat_type`** — migration `2026_06_06_074023_add_chat_type_to_telegram_chats_table.php`; indexed column on `telegram_chats`.
3. **Nullable `parser_type`** — same migration removes `standard_dispute` default; column default is `null`.
4. **Backfill** — existing rows with `parser_type = standard_dispute` set to `chat_type = dispute_processing`.
5. **DB defaults for new rows** — `chat_type` and `parser_type` default to `null` at schema level; webhook ingestion aligned in Phase 3.
6. **`telegram_chat_traders` pivot** — migration `2026_06_06_074023_create_telegram_chat_traders_table.php` with `telegram_chat_id`, `trader_id`, nullable `telegram_username`, unique pair, indexes.

Also shipped ahead of Phase 2 scope: `TelegramChat` `$fillable` and `casts()` for `chat_type`; nullable `parser_type` in PHPDoc.

### Phase 2: Models and Resources — **Done**

Shipped in code (2026-06-06):

1. **`TelegramChat::traders()`** — `belongsToMany` via `telegram_chat_traders`; pivot `telegram_username`; `withTimestamps()`.
2. **`User::telegramTeamChats()`** — inverse relationship with same pivot.
3. **`TelegramChatTraderResource`** — `id`, `email`, `telegram_username`, `telegram_tag`, pivot timestamps.
4. **`TelegramChatResource`** — `chat_type`, nullable-safe `parser_type`, `team_traders` via `whenLoaded('traders')` with `->resolve()`.
5. **`TelegramChatController::index()`** — selected chat eager loads `traders` ordered by `users.email`; paginated list unchanged.

### Phase 3: Webhook and Processing Gates — **Done**

Shipped in code (2026-06-06):

1. **`TelegramChatWebhookIngestionService::resolveTelegramChat()`** — new chats created with `chat_type = null`, `parser_type = null`, `status = pending_moderation`, `debug_enabled = true`; removed `TelegramChatParserType::STANDARD_DISPUTE` default.
2. **`TelegramChat::canProcessDisputeMessages()`** — domain gate: `active` + not `trader_team` + `parser_type = standard_dispute` + (`chat_type` null or `dispute_processing`).
3. **`ProcessTelegramChatMessageJob`** — `storeDebugAttachmentsIfNeeded()` before gate; `process()` only when `canProcessDisputeMessages()`.
4. **`TelegramChatMessageProcessor::process()`** — same gate; null-safe skip log with `chat_type` and `parser_type`.
5. **`redispatchReceivedMessages()`** — unchanged; safe no-op for unassigned/team chats until configured.

**Not changed in Phase 3:** admin `UpdateRequest` (still `parser_type` only), membership CRUD, notification jobs, automated tests.

### Phase 4: Admin Backend — **Done**

Shipped in code (2026-06-06):

1. **`UpdateRequest`** — accepts nullable `chat_type` (`TelegramChatType` enum); empty string → `null`; keeps transitional `parser_type` for legacy frontend.
2. **`TelegramChatController::resolveChatConfiguration()`** — derives `parser_type` from `chat_type`; legacy `parser_type`-only path maps to `dispute_processing` or unassigned.
3. **`TelegramChatController::index()`** — new `chatTypes` Inertia prop for Phase 5 UI.
4. **`TelegramChatTraderController`** — search, store, update, destroy for team membership.
5. **Routes** — `trader-search`, `traders.store`, `traders.update`, `traders.destroy` in Super Admin group.
6. **Form Requests** — `SearchTraderRequest`, `StoreTraderRequest`, `UpdateTraderRequest` with Trader role, unique membership, and `trader_team` chat type guards.
7. **`TelegramUsernameNormalizer`** — shared normalize + validation regex for pivot `telegram_username`.

**Not changed in Phase 4:** admin frontend, notification jobs, automated tests.

### Phase 5: Admin Frontend — **Done**

Shipped in code (2026-06-06):

1. **`Index.vue` chat settings** — `chat_type` selector from `chatTypes` prop; status + debug unchanged; save via `PATCH` with `processing` state.
2. **Parser hidden** — removed `parserTypes` prop from controller; badge shows `chatTypeLabel()` instead of raw `parser_type`.
3. **Trader team block** — shown when form selects `trader_team`; CRUD gated on saved `selectedChat.chat_type === 'trader_team'`.
4. **Members table** — `team_traders` list with per-row username edit (`traders.update`) and remove (`ConfirmModal` + `traders.destroy`).
5. **Add trader** — debounced search (`trader-search`, 300 ms), dropdown filters existing members, optional username, `traders.store`.
6. **DaisyUI/Tailwind** — fieldset/select/input/table patterns consistent with existing admin page; `ConfirmModal` for removal.

**Not changed in Phase 5:** dispute notification jobs, reminder scheduling, automated tests, Phase 8 manual verification.

### Phase 6: Dispute Notification Jobs — **Done**

Shipped in code (2026-06-06):

1. **`TelegramTraderTeamDisputeNotificationType` enum** — `immediate`, `fifteen_minute`, `hourly`.
2. **`TelegramTraderTeamDisputeNotificationService`** — find active team chats, pending check, message build, per-chat send with fault isolation and structured logging.
3. **`SendTelegramTraderTeamDisputeNotificationJob`** — immediate send; schedules 15-minute reminder.
4. **`SendTelegramTraderTeamDisputeReminderJob`** — 15-minute and hourly reminders; reloads dispute state at execution time.
5. **`SendTelegramTraderTeamDisputeNotificationListener`** — dispatches immediate job on `DisputeOpenedEvent` (auto-discovered).

**Not changed in Phase 6:** notification log table, automated tests, Phase 8 verification.

### Phase 7: Scheduling Strategy — **Done**

Shipped in code (2026-06-06) inside reminder job:

- Immediate job → `SendTelegramTraderTeamDisputeReminderJob` (`fifteen_minute`) with `delay(now()->addMinutes(15))`.
- Reminder job → after pending check and send, self-dispatches `HOURLY_REMINDER` with `delay(now()->addHour())`.
- Chain stops when reloaded dispute is no longer `PENDING` (accept/cancel/rollback/missing).

No global scheduler scan.

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
