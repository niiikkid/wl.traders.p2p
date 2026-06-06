# Telegram Chat Types and Trader Team Notifications — Phase 5 (Admin Frontend)

> Source: Cursor implementation session (p2p.cti)
> Collected: 2026-06-06
> Published: Unknown

## Summary

Phase 5 of the Trader Team Notifications feature: Super Admin UI on `Admin/TelegramChats/Index.vue` for chat function selection (`chat_type`) and trader team membership management. Uses Phase 4 backend routes and Inertia props. Notification jobs not implemented yet (Phase 6–7).

## Chat Settings Panel

### Replaced parser selector with chat function selector

- Form field `chat_type` (was `parser_type`) saved via `PATCH admin.telegram-chats.update`
- Options from Inertia prop `chatTypes`: «Не назначен» (`''`), «Споры» (`dispute_processing`), «Команда трейдеров» (`trader_team`)
- Empty string sent for unassigned; backend `UpdateRequest::prepareForValidation()` normalizes to `null`
- Parser implementation hidden from UI; header badge shows human-readable function label via `chatTypeLabel()`
- Removed unused `parserTypes` prop from `TelegramChatController::index()`

### Unchanged in settings panel

- Status select (`chatStatuses`)
- Debug toggle with `ConfirmModal` when disabling
- Save button with `processing` state

## Trader Team Membership UI

Visible when form `chat_type === 'trader_team'`. Membership CRUD enabled only after chat is saved with `selectedChat.chat_type === 'trader_team'` (warning alert otherwise).

### Members table

- Data from `selectedChat.team_traders` (eager-loaded via `TelegramChatResource`)
- Columns: trader `email`, editable `telegram_username` (without `@`), per-row save, remove
- Username hint: «Без @. Для упоминания в уведомлениях.»

### Add trader block

- Backend search by email: `GET admin.telegram-chats.trader-search` with debounce 300 ms
- Response shape: `{ traders: [{ id, email }, ...] }`
- Dropdown excludes traders already in `team_traders`
- Optional `telegram_username` on add
- `POST admin.telegram-chats.traders.store` via `useForm` with `processing` on button
- Validation errors shown for `trader_id` and `telegram_username`

### Edit / remove

- `PATCH admin.telegram-chats.traders.update` per member with `updatingTraderId` loading state
- `DELETE admin.telegram-chats.traders.destroy` via `ConfirmModal` (title/body in Russian)

## Files Changed

- `resources/js/Pages/Admin/TelegramChats/Index.vue` — chat function selector, team block, trader search, membership CRUD
- `app/Http/Controllers/Admin/TelegramChatController.php` — removed `parserTypes` Inertia prop

## Not in Phase 5

- Dispute notification jobs (Phase 6)
- Reminder scheduling (Phase 7)
- End-to-end manual verification checklist (Phase 8)
- Automated tests (deferred per project rule)
