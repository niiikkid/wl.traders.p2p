# Phase 5 — Admin UI Implementation

> Source: Project implementation (conversation + codebase)
> Collected: 2026-05-21
> Published: 2026-05-21

## Summary

Phase 5 delivers the Super Admin Inertia page for managing Telegram chat automation: bot status and webhook controls, chat list with active/archive tabs, per-chat moderation and settings, message history with detail modal, and protected attachment downloads. Bot settings continue to use the Phase 2 JSON API via axios from the UI.

## Page

- Route: `GET /admin/telegram-chats` (`admin.telegram-chats.index`)
- Vue: `resources/js/Pages/Admin/TelegramChats/Index.vue`
- Menu: `AdminMenu.vue` — item «Telegram-чаты»
- Layout: two-column on `xl` — `MainTableSection` (chat table) + detail card (selected chat)

## Query parameters

- `tab` — `active` (default) or `archived`
- `chat` — local `telegram_chats.id` for selected chat detail and messages
- `messages_page` — pagination for messages in detail panel
- `per_page` — chat list pagination (via `MainTableSection` / `tableFilters`)

## Backend

### Controllers

- `Admin\TelegramChatController` — `index` (Inertia), `messages` (JSON), `update`, `archive`, `restore`, `toggleDebug`
- `Admin\TelegramChatAttachmentController` — `show` streams file from `local` disk after relation checks

### Resources

- `TelegramChatResource` — chat list/detail fields including `display_title`, `messages_count`, `last_message_status`, `last_failure_reason`
- `TelegramChatMessageResource` — message list/detail with `order_uuid`, attachments
- `TelegramChatMessageAttachmentResource` — `download_url` via `admin.telegram-chats.messages.attachments.show`

### Form requests

- `Admin\TelegramChat\UpdateRequest` — `status`, `parser_type` (enum validation)
- `Admin\TelegramChat\ToggleDebugRequest` — `debug_enabled` (boolean)

### Model addition

- `TelegramChat::latestMessage()` — `hasOne` `latestOfMany()` for last processing status in table

## Admin routes (Super Admin)

- `GET admin/telegram-chats` — `admin.telegram-chats.index`
- `GET admin/telegram-chats/{telegramChat}/messages` — `admin.telegram-chats.messages.index` (JSON)
- `PATCH admin/telegram-chats/{telegramChat}` — `admin.telegram-chats.update`
- `POST admin/telegram-chats/{telegramChat}/archive` — `admin.telegram-chats.archive`
- `POST admin/telegram-chats/{telegramChat}/restore` — `admin.telegram-chats.restore`
- `PATCH admin/telegram-chats/{telegramChat}/debug` — `admin.telegram-chats.debug.update`
- `GET admin/telegram-chats/{telegramChat}/messages/{telegramChatMessage}/attachments/{attachment}` — `admin.telegram-chats.messages.attachments.show`

Bot settings (Phase 2, used from UI via axios):

- `admin.telegram-bot.settings.show` / `.update` / `webhook.setup`

## UI behavior

- Header: bot status badge, «Настройки бота» modal (axios load/save settings), «Установить webhook» (axios, disabled without token)
- Tabs: Активные / Архив (filters chats by `status !== archived` vs `archived`)
- Chat table: title, Telegram chat ID, status, debug flag, message count, last message time/status; row click selects chat
- Row actions: activate / disable / archive (active tab); restore (archive tab)
- Detail panel: status + parser selects, save via Inertia PATCH; debug toggle with `ConfirmModal` when disabling (cleanup job deferred to Phase 6)
- Messages table in detail with pagination; «Подробнее» opens modal (raw payload, attachments with download links)
- Attachment download: Super Admin only; verifies chat → message → attachment chain; `Storage::disk('local')->response()`

## Not in Phase 5

- `CleanupTelegramChatDebugMessagesJob` — Phase 6; debug-off confirmation warns that accumulated debug messages are not deleted yet

## Post-change commands

- `php artisan optimize`
- `php artisan ziggy:generate resources/js/ziggy-routes.js`
