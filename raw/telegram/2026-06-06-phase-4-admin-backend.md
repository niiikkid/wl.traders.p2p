# Telegram Chat Types and Trader Team Notifications — Phase 4 (Admin Backend)

> Source: Cursor implementation session (p2p.cti)
> Collected: 2026-06-06
> Published: Unknown

## Summary

Phase 4 of the Trader Team Notifications feature: Super Admin backend for chat function selection (`chat_type`) with derived `parser_type`, trader search for membership UI, and membership CRUD on `telegram_chat_traders`. Admin frontend (`Index.vue`) not updated yet (Phase 5).

## Chat Update — `chat_type` + Derived `parser_type`

### `UpdateRequest`

- Accepts `chat_type` (nullable enum `TelegramChatType`; empty string normalized to `null`)
- Keeps transitional `parser_type` validation for legacy frontend
- `status` validation unchanged

### `TelegramChatController::resolveChatConfiguration()`

When `chat_type` is present in validated input:

- `dispute_processing` → `parser_type = standard_dispute`
- `trader_team` or `null` (unassigned) → `parser_type = null`

Legacy path when only `parser_type` is sent (current `Index.vue`):

- `standard_dispute` → `chat_type = dispute_processing`, keep parser
- otherwise → `chat_type = null`, `parser_type = null`

Status-only updates (activate/disable) do not touch `chat_type`/`parser_type`.

### `TelegramChatController::index()`

New Inertia prop `chatTypes`:

- `''` → «Не назначен»
- `dispute_processing` → «Споры»
- `trader_team` → «Команда трейдеров»

## Trader Membership — `TelegramChatTraderController`

Dedicated controller (not nested in `TelegramChatController`).

### Routes (Super Admin group)

| Method | Route name | Action |
|--------|------------|--------|
| GET | `admin.telegram-chats.trader-search` | Search traders by email |
| POST | `admin.telegram-chats.traders.store` | Add trader to team chat |
| PATCH | `admin.telegram-chats.traders.update` | Update pivot `telegram_username` |
| DELETE | `admin.telegram-chats.traders.destroy` | Remove trader from team chat |

`trader-search` registered before `{telegramChat}` routes to avoid route conflict.

### Search (`search`)

- Query param: `query` (nullable, max 100)
- Filters: `User::role('Trader')`, ordered by `email`, limit 20
- Search: `email LIKE %query%` when query non-empty
- Response JSON: `{ traders: [{ id, email }, ...] }`

### Store (`store`)

Form Request: `StoreTraderRequest`

- `trader_id`: required, exists in `users`, unique per chat in `telegram_chat_traders`
- `telegram_username`: optional, regex `^@?[A-Za-z0-9_]{5,32}$`, normalized without `@`
- Validates chat has `chat_type = trader_team`
- Validates user has Trader role (Spatie `role('Trader')`)
- Uses `attach()` on pivot

### Update (`update`)

Form Request: `UpdateTraderRequest`

- `telegram_username`: optional, same validation/normalization as store
- Validates chat is `trader_team`
- 404 if trader not attached to chat
- Uses `updateExistingPivot()`

### Destroy (`destroy`)

- 404 if trader not attached
- Uses `detach()`

All membership mutations return `RedirectResponse` with flash message (Inertia pattern).

## Telegram Username Normalization

New `App\Support\TelegramUsernameNormalizer`:

- `normalize(?string): ?string` — trim, strip leading `@`, empty → `null`
- `VALIDATION_PATTERN` — shared regex constant used in Form Requests

## Form Request Files

- `SearchTraderRequest`
- `StoreTraderRequest`
- `UpdateTraderRequest`

Note: spec suggested `StoreTelegramChatTraderRequest` naming; implementation uses shorter names under `Admin/TelegramChat/` namespace.

## Files Changed / Added

- `app/Http/Controllers/Admin/TelegramChatController.php` — `resolveChatConfiguration()`, `chatTypes` prop
- `app/Http/Controllers/Admin/TelegramChatTraderController.php` (new)
- `app/Http/Requests/Admin/TelegramChat/UpdateRequest.php`
- `app/Http/Requests/Admin/TelegramChat/SearchTraderRequest.php` (new)
- `app/Http/Requests/Admin/TelegramChat/StoreTraderRequest.php` (new)
- `app/Http/Requests/Admin/TelegramChat/UpdateTraderRequest.php` (new)
- `app/Support/TelegramUsernameNormalizer.php` (new)
- `routes/web.php` — four new routes

Post-change: `php artisan optimize`, `php artisan ziggy:generate`.

## Not in Phase 4

- Admin frontend chat function selector and membership UI (Phase 5)
- Dispute notification jobs (Phase 6–7)
- Automated tests (deferred per project rule)
