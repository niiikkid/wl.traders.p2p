# Telegram Chat Dispute Automation Plan

> Sources: User conversation, 2026-05-21; Telegram Bot API documentation, 2026-05-21; Phase 1 implementation, 2026-05-21; Phase 2 implementation, 2026-05-21
> Raw: [Telegram Chat Dispute Automation Requirements](../../raw/telegram/2026-05-21-telegram-chat-dispute-automation-requirements.md)
> Updated: 2026-05-21

## Overview

The feature adds a separate admin-managed Telegram bot for processing merchant chat messages and automatically opening disputes for `Order` records when a message contains a valid receipt file and a matching order UUID. The design uses universal but explicit Telegram chat models, stores chat/message history safely, keeps files private, processes webhook updates asynchronously, and isolates this flow from Cascade completely.

## Goals

- Create a separate Telegram bot integration for chat automation, independent from the existing notification bot.
- Detect dispute messages in Telegram chats using a receipt attachment plus an `Order` UUID.
- Open disputes automatically through the existing dispute service flow.
- Store all dispute-related messages and files for admin review.
- Optionally store all chat messages per chat when debug mode is enabled.
- Provide a Super Admin UI for bot settings, webhook setup, chat moderation, chat configuration, and message viewing.
- Keep future extensibility for other chat automation cases without naming the data model only around disputes.

## Non-Goals

- Do not support Cascade deals or Cascade disputes.
- Do not bind chats to merchants in the first version.
- Do not parse UUIDs from replies, forwarded messages, threads, or nested Telegram entities yet.
- Do not implement broad Telegram bot replies except the success message after dispute creation.
- Do not write or run tests unless explicitly requested later.

## Proposed Naming

Use explicit Telegram-oriented names:

- `TelegramChat`
- `TelegramChatMessage`

This keeps the model understandable while avoiding names such as `DisputeChat`, which would make future chat automation harder to extend.

**Naming note (implemented):** `TelegramChat.telegram_chat_id` is the Telegram API chat identifier (string, unique). `TelegramChatMessage.telegram_chat_id` is a foreign key to `telegram_chats.id` — same column name, different meaning on each model.

## Data Model

### Telegram Bot Settings

Create a database-backed settings model or setting record dedicated to this bot. The settings should include:

- `bot_token`, encrypted.
- `webhook_secret`, encrypted or securely stored.
- `webhook_set_at`, nullable timestamp.
- `webhook_last_error`, nullable text.
- Optional webhook metadata from Telegram, such as current URL or pending update count, if useful for diagnostics. Stored as JSON column `webhook_metadata`.

The bot token must not be managed through `.env`.

### TelegramChat

Suggested fields:

- `id`
- `telegram_chat_id`, unique string or signed bigint-safe string.
- `type`, nullable string from Telegram chat type.
- `title`, nullable string.
- `username`, nullable string.
- `status`, enum: `pending_moderation`, `active`, `disabled`, `archived`.
- `parser_type`, string, default `standard_dispute`.
- `debug_enabled`, boolean, default `false`.
- `last_message_at`, nullable timestamp.
- `raw_payload`, nullable JSON for the first or latest chat metadata if needed.
- `created_at`
- `updated_at`

Indexes:

- Unique index on `telegram_chat_id`.
- Index on `status`.
- Index on `debug_enabled` if cleanup/filtering needs it.

### TelegramChatMessage

Suggested fields:

- `id`
- `telegram_chat_id`, foreign key to local `telegram_chats.id`.
- `telegram_update_id`, nullable string or bigint-safe string.
- `telegram_message_id`, string or bigint-safe string.
- `message_type`, enum or string: `text`, `photo`, `document`, `unknown`.
- `text`, nullable text.
- `caption`, nullable text.
- `detected_uuid`, nullable string.
- `order_id`, nullable foreign key to `orders.id`.
- `dispute_id`, nullable foreign key to `disputes.id`.
- `status`, enum: `received`, `ignored`, `matched`, `processed`, `failed`, `duplicate`.
- `failure_reason`, nullable text.
- `is_dispute_related`, boolean, default `false`.
- `raw_payload`, nullable JSON.
- `processed_at`, nullable timestamp.
- `created_at`
- `updated_at`

Indexes:

- Unique index on `telegram_update_id` where possible.
- Unique index on `telegram_chat_id + telegram_message_id`.
- Index on `order_id`.
- Index on `dispute_id`.
- Index on `is_dispute_related`.
- Index on `status`.

### Attachments

There are two reasonable options:

- Add attachment columns directly to `TelegramChatMessage` if only one file is supported at first.
- Add `TelegramChatMessageAttachment` for a cleaner future-proof model.

Prefer a separate attachment model because Telegram photos may have multiple sizes and future use cases may include multiple files.

Suggested `TelegramChatMessageAttachment` fields:

- `id`
- `telegram_chat_message_id`
- `telegram_file_id`
- `telegram_file_unique_id`, nullable.
- `original_name`, nullable.
- `stored_name`
- `mime_type`
- `extension`
- `size`
- `storage_path`
- `created_at`
- `updated_at`

Store files under a private folder such as `storage/app/telegram-chat-attachments`.

## Chat Status Behavior

### pending_moderation

Default for new chats. The system creates a chat record when the first update arrives from an unknown chat. Messages should be saved only if they are dispute-related or debug mode is enabled, but dispute processing should not run until the chat is active.

### active

The chat is allowed to parse messages and open disputes.

### disabled

The chat is known but temporarily does not parse messages. Dispute processing is skipped. Debug storage can still follow the chat's `debug_enabled` setting.

### archived

The chat is considered not needed. It should be hidden from the main list and available through an archive filter. Processing is skipped.

## Webhook Design

Create one dedicated webhook endpoint for the new bot, separate from the existing notification Telegram webhook.

Recommended route:

- `POST /telegram/chat-automation/webhook`

Security:

- Use Telegram Bot API `setWebhook` with `secret_token`.
- Verify incoming requests by comparing the `X-Telegram-Bot-Api-Secret-Token` header against the stored webhook secret.
- Reject invalid requests with `403`.

Allowed updates:

- Start with `message`.
- Avoid subscribing to unrelated update types until needed.

Webhook execution should be fast:

1. Validate the secret header.
2. Read the update payload.
3. Enforce idempotency by `telegram_update_id`.
4. Create or update the `TelegramChat`.
5. Store a minimal message row when appropriate.
6. Dispatch a job for file download, parsing, dispute creation, and Telegram reply.
7. Return `204 No Content`.

## Idempotency

Use both required keys:

- `telegram_update_id`
- `telegram_chat_id + telegram_message_id`

Behavior:

- If the same update arrives twice, do not dispatch duplicate processing.
- If another update references the same chat/message pair, do not create a second message row.
- If processing is retried by the queue, the job must re-check message status and existing dispute state before creating a dispute.

## Parsing Algorithm

The standard dispute parser should follow this flow:

1. Extract message text from the main Telegram message body:
   - Prefer `text`.
   - Fall back to `caption`.
2. Extract UUID candidates using a strict UUID regex compatible with `orders.uuid`.
3. If there are no UUID candidates:
   - If debug is enabled, store as ignored.
   - If debug is disabled and it is not dispute-related, do not retain it.
4. Query matching orders with `Order::whereIn('uuid', $uuidCandidates)`.
5. If no `Order` is found:
   - Save the message as `failed`.
   - Set `failure_reason` to a clear reason such as `Order UUID not found`.
6. If more than one `Order` is found:
   - Save the message as `failed`.
   - Set `failure_reason` to a clear reason such as `Multiple matching order UUIDs found`.
7. If exactly one `Order` is found:
   - Mark `detected_uuid`.
   - Link `order_id`.
   - Continue file validation.

Regex candidate:

```regex
\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b
```

If the project confirms that `orders.uuid` is always lowercase, normalize candidates to lowercase before querying.

## File Validation

The message must contain an acceptable attachment:

- Image: `jpg`, `jpeg`, `png`.
- PDF: `pdf`.
- Maximum size: 5 MB.

Validation should check both:

- Telegram metadata such as MIME type, file name, and file size when available.
- Downloaded file MIME/extension using Laravel validation or Symfony file inspection before opening a dispute.

Reject all other files and mark the message as `failed` with a clear reason. Do not store rejected unsafe files unless there is a strong diagnostic reason; metadata and raw payload are enough.

## File Storage and Access

Files must be private:

- Store under `storage/app/telegram-chat-attachments`.
- Do not symlink this folder into `public`.
- Do not expose direct file paths in the UI.

Provide protected admin routes:

- `GET /admin/telegram-chats/{telegramChat}/messages/{telegramChatMessage}/attachments/{attachment}`

This endpoint must:

- Require `Super Admin`.
- Verify that the attachment belongs to the message and chat.
- Stream or download the file from private storage.
- Return `404` if the file is missing or relation checks fail.

When a dispute is opened, the existing dispute service expects an uploaded file-like object. The processing job can create an `UploadedFile` from the private stored file and pass it to `services()->dispute()->create($order->id, $uploadedFile)`, preserving the existing receipt flow.

## Dispute Creation Flow

For an active chat and a valid parser result:

1. Lock or safely load the matched `Order`.
2. If the order already has a dispute:
   - Mark the Telegram message as `duplicate`.
   - Link `order_id`.
   - Do not send a Telegram success message.
3. If there is no existing dispute:
   - Call the existing dispute service with the receipt file.
   - Mark the Telegram message as `processed`.
   - Link `dispute_id`.
   - Send a Telegram success reply.

Suggested success text:

```text
Спор открыт.
UUID сделки: <uuid>
```

The bot should not send replies for failed, ignored, disabled, archived, or duplicate messages in the first version.

## Debug Mode

Debug mode is controlled per `TelegramChat`.

When enabled:

- Store all incoming messages for that chat.
- Store text, file metadata, allowed files, and raw payload.
- Continue to always process dispute-related messages if the chat is active.

When disabled:

- Store only dispute-related messages.
- Non-dispute messages should no longer be retained.

When turning debug mode off:

1. Show a confirmation modal in the admin UI.
2. Dispatch a cleanup job.
3. The cleanup job deletes only messages for that chat where `is_dispute_related = false`.
4. Delete only files referenced by those messages' attachments.
5. Restrict deletion to the Telegram private storage folder.
6. Do not delete dispute-related messages, dispute receipts, or any files outside the Telegram attachment folder.

## Admin UI Plan

Use one Super Admin page, for example:

- `Admin/TelegramChats/Index.vue`

Main page sections:

- Header with bot status and "Настройки бота" modal button.
- Button to install/update webhook.
- Chat list table.
- Archive filter or tab.
- Per-chat actions: activate, disable, archive, restore if needed, open messages.

Bot settings modal:

- Bot token password field.
- Webhook secret regeneration or display of configured status.
- Save button with `processing` disabled state.
- Webhook status information if available.

Chat list columns:

- Telegram chat title.
- Telegram chat ID.
- Status.
- Parser type.
- Debug mode.
- Last message time.
- Message count.
- Last processing status or last error.
- Actions.

Chat detail view can be on the same route via selected chat state or a nested admin route. It should show:

- Chat metadata.
- Status controls.
- Parser type selector, default `standard_dispute`.
- Debug mode toggle with destructive confirmation when disabling.
- Message list.
- Message detail drawer/modal with raw payload, processing status, failure reason, detected UUID, linked order, linked dispute, and attachment links.

## Backend Structure

Suggested classes (implemented in **bold**):

- `Admin\TelegramChatController`
- **`Admin\TelegramBotSettingController`**
- `TelegramChatWebhookController` (public route uses **`TelegramChatAutomationWebhookController`** placeholder until Phase 3)
- `TelegramChatAttachmentController`
- `ProcessTelegramChatMessageJob`
- `CleanupTelegramChatDebugMessagesJob`
- `TelegramChatMessageParser`
- `StandardTelegramDisputeParser`
- `TelegramChatFileService`
- **`TelegramChatBotService`** (`TelegramChatBotServiceContract`, registered as `services()->telegramChatBot()`)

Suggested enums:

- `TelegramChatStatus`
- `TelegramChatParserType`
- `TelegramChatMessageStatus`
- `TelegramChatMessageType`

Suggested form requests (implemented in **bold**):

- **`Admin\TelegramBotSetting\UpdateRequest`**
- `Admin\TelegramChat\UpdateRequest`
- `Admin\TelegramChat\ToggleDebugRequest`

## Routing Plan

Webhook route outside auth (registered; ingestion logic in Phase 3):

- **`POST /telegram/chat-automation/webhook`** — route name `telegram.chat-automation.webhook`; middleware `telegram.chat-automation.secret` + `backoffice.domain`; CSRF excluded; currently returns `204` from placeholder controller

Admin routes inside `admin` prefix and `role:Super Admin` group:

- `GET /admin/telegram-chats`
- `PATCH /admin/telegram-chats/{telegramChat}`
- `POST /admin/telegram-chats/{telegramChat}/archive`
- `POST /admin/telegram-chats/{telegramChat}/restore`
- `PATCH /admin/telegram-chats/{telegramChat}/debug`
- `GET /admin/telegram-chats/{telegramChat}/messages`
- `GET /admin/telegram-chats/{telegramChat}/messages/{telegramChatMessage}/attachments/{attachment}`
- **`GET /admin/telegram-bot/settings`** — `admin.telegram-bot.settings.show` (JSON)
- **`PATCH /admin/telegram-bot/settings`** — `admin.telegram-bot.settings.update` (JSON)
- **`POST /admin/telegram-bot/webhook`** — `admin.telegram-bot.webhook.setup` (JSON)

If routes are changed, run project-required route commands during implementation:

- `php artisan optimize`
- `php artisan ziggy:generate resources/js/ziggy-routes.js`

## Queue and Reliability

Use queued jobs for:

- File download from Telegram.
- File validation after download.
- Message parsing and dispute creation.
- Debug cleanup.

Reliability rules:

- Jobs must be idempotent.
- Jobs should not assume the chat is still active; re-check status before opening disputes.
- Jobs should handle missing Telegram files gracefully and mark the message failed.
- External Telegram API calls should have timeouts and clear error handling.
- If sending the success Telegram reply fails after dispute creation, do not roll back the dispute; store/send failure diagnostics on the message if needed.

## Implementation Status

| Phase | Status | Notes |
|-------|--------|-------|
| 1 — Database and domain types | **Done** (2026-05-21) | Migrations applied; models and enums in `app/` |
| 2 — Bot settings and webhook setup | **Done** (2026-05-21) | Service, admin JSON API, `setWebhook` / `getWebhookInfo`; public webhook route + secret middleware |
| 3 — Webhook ingestion | Pending | Route and middleware exist; controller is placeholder only |
| 4 — Message processing | Pending | |
| 5 — Admin UI | Pending | |
| 6 — Cleanup and hardening | Pending | |

### Phase 1 artifacts (implemented)

**Migrations** (2026-05-21):

- `database/migrations/2026_05_21_145149_create_telegram_bot_settings_table.php`
- `database/migrations/2026_05_21_145151_create_telegram_chats_table.php`
- `database/migrations/2026_05_21_145152_create_telegram_chat_messages_table.php`
- `database/migrations/2026_05_21_145153_create_telegram_chat_message_attachments_table.php`

**Enums** (`app/Enums/`):

- `TelegramChatStatus` — `pending_moderation`, `active`, `disabled`, `archived`
- `TelegramChatParserType` — `standard_dispute`
- `TelegramChatMessageType` — `text`, `photo`, `document`, `unknown`
- `TelegramChatMessageStatus` — `received`, `ignored`, `matched`, `processed`, `failed`, `duplicate`

**Models** (`app/Models/`):

- `TelegramBotSetting` — `bot_token` and `webhook_secret` use `encrypted` cast; helpers `hasBotToken()`, `hasWebhookSecret()`
- `TelegramChat` — `messages()` has-many
- `TelegramChatMessage` — `telegramChat()`, `order()`, `dispute()`, `attachments()`
- `TelegramChatMessageAttachment` — `telegramChatMessage()`

**MySQL index naming:** auto-generated unique/FK names exceeded the 64-character limit. Short names used in migrations: `tg_chat_messages_chat_msg_unique` (composite unique on `telegram_chat_id` + `telegram_message_id`), `tg_chat_msg_att_msg_fk` (attachments → messages).

### Phase 2 artifacts (implemented)

**Service** (`app/Services/Telegram/`):

- `TelegramChatBotService` — `getSettings()`, `updateSettings()`, `setupWebhook()`, `refreshWebhookMetadata()`, `webhookUrl()`; Telegram HTTP API via `Http` client (`setWebhook`, `getMe`, `getWebhookInfo`); honors `config('telegram.proxy')`
- `TelegramChatBotServiceContract` — bound in `AppServiceProvider`, exposed as `services()->telegramChatBot()`
- `TelegramChatBotException` — validation and webhook setup errors

**Admin API** (JSON responses, secrets never exposed):

- `Admin\TelegramBotSettingController` — `show`, `update`, `setupWebhook`
- `TelegramBotSettingResource` — `has_bot_token`, `has_webhook_secret`, `webhook_set_at`, `webhook_last_error`, `webhook_url`, sanitized `webhook_metadata`
- `Admin\TelegramBotSetting\UpdateRequest` — `bot_token` (optional), `regenerate_webhook_secret` (optional boolean)

**Webhook security and route:**

- `VerifyTelegramChatAutomationSecretToken` — compares `X-Telegram-Bot-Api-Secret-Token` to encrypted `webhook_secret` from DB (alias `telegram.chat-automation.secret`)
- `TelegramChatAutomationWebhookController` — placeholder `204 No Content` until Phase 3

**Behavior notes:**

- `bot_token` validated with `getMe` before save; token/secret changes clear `webhook_set_at` and metadata until webhook is set again
- `webhook_secret` auto-generated on first settings save or when `regenerate_webhook_secret` is true; charset compatible with Telegram `secret_token` rules
- `setupWebhook()` calls `setWebhook` with `url` from `route('telegram.chat-automation.webhook')`, `secret_token`, `allowed_updates: ['message']`, `drop_pending_updates: true`; stores result in `webhook_set_at`, `webhook_last_error`, `webhook_metadata`
- `show` refreshes `webhook_metadata` via `getWebhookInfo` when token is configured (silent fallback on API errors)
- Separate from notification bot (`TelegramService`, `config/telegram.php`, `POST /telegram/webhook`)

## Implementation Phases

### Phase 1: Database and Domain Types — Done

- [x] Migrations for `telegram_bot_settings`, `telegram_chats`, `telegram_chat_messages`, `telegram_chat_message_attachments`
- [x] Models and relationships
- [x] Encrypted casts for `bot_token` and `webhook_secret` on `TelegramBotSetting`
- [x] Enums for chat status, parser type, message type, and message status

### Phase 2: Bot Settings and Webhook Setup — Done

- [x] Settings service for the new bot (`TelegramChatBotService`)
- [x] Admin JSON endpoints for reading/updating bot settings
- [x] Webhook setup action using Telegram `setWebhook` with `secret_token`
- [x] Store webhook setup result and errors (`webhook_set_at`, `webhook_last_error`, `webhook_metadata`)
- [x] Public webhook route and DB-backed secret middleware (placeholder controller until Phase 3)

### Phase 3: Webhook Ingestion

- Replace placeholder `TelegramChatAutomationWebhookController` with full ingestion logic (or rename to planned `TelegramChatWebhookController`)
- Verify `X-Telegram-Bot-Api-Secret-Token` (middleware already implemented in Phase 2)
- Parse incoming update.
- Create or update `TelegramChat`.
- Apply idempotency.
- Store initial message rows where needed.
- Dispatch processing jobs.

### Phase 4: Message Processing

- Implement file extraction for Telegram `photo` and `document`.
- Download the selected file.
- Validate extension, MIME type, and size.
- Store allowed files privately.
- Extract UUID candidates from text/caption.
- Query `Order::whereIn`.
- Mark failed/duplicate/processed statuses with reasons.
- Open disputes through the existing dispute service.
- Send Telegram success message after dispute creation.

### Phase 5: Admin UI

- Build one Super Admin page.
- Add bot settings modal.
- Add webhook setup button.
- Add chat table with status/archive filters.
- Add chat detail/message list.
- Add protected attachment links.
- Add debug mode toggle with confirmation modal.

### Phase 6: Cleanup and Hardening

- Implement asynchronous cleanup when debug mode is disabled.
- Ensure cleanup deletes only non-dispute messages for one chat.
- Ensure file deletion is restricted to Telegram attachment files.
- Add logging for processing errors.
- Run formatter for modified PHP files during implementation.

## Open Follow-Ups

- Decide later whether to parse replies, forwarded messages, media groups, and thread-specific messages.
- Decide later whether failed messages should trigger Telegram replies.
- Decide later whether chats should be bindable to merchants for extra validation.
- Decide later whether to add more parser types beyond `standard_dispute`.
