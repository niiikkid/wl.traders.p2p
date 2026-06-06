# Telegram Chat Types and Trader Team Notifications — Phase 2 (Models and Resources)

> Source: Cursor implementation session (p2p.cti)
> Collected: 2026-06-06
> Published: Unknown

## Summary

Phase 2 of the Trader Team Notifications feature: Eloquent relationships for trader team chat membership, API resource shape for team traders, and admin selected-chat eager loading. No webhook gating, membership CRUD endpoints, admin UI, or notification jobs yet.

## Models

### `TelegramChat::traders()`

- `belongsToMany(User::class, 'telegram_chat_traders', 'telegram_chat_id', 'trader_id')`
- `withPivot('telegram_username')`
- `withTimestamps()`
- PHPDoc: `@property-read Collection<int, User> $traders`

### `User::telegramTeamChats()`

- `belongsToMany(TelegramChat::class, 'telegram_chat_traders', 'trader_id', 'telegram_chat_id')`
- `withPivot('telegram_username')`
- `withTimestamps()`

## Resources

### `app/Http/Resources/TelegramChatTraderResource.php` (new)

Shape per team-trader membership (wraps `User` from pivot):

- `id` — trader user id
- `email` — display field per project convention
- `telegram_username` — from pivot, nullable
- `telegram_tag` — `@username` when username set, else null
- `created_at`, `updated_at` — pivot timestamps as datetime strings

### `app/Http/Resources/TelegramChatResource.php` (updated)

- `chat_type` — nullable enum value (`$this->chat_type?->value`)
- `parser_type` — now nullable-safe (`$this->parser_type?->value`)
- `team_traders` — included only when `traders` relation is eager loaded; uses `TelegramChatTraderResource::collection(...)->resolve()` for Inertia non-paginated props

## Controller

### `TelegramChatController::index()`

Selected chat query now eager loads:

```php
'traders' => fn ($query) => $query->orderBy('users.email'),
```

Paginated chat list does not load `traders` (avoids unnecessary queries).

## Verification

Relationships resolve to `BelongsToMany` on pivot table `telegram_chat_traders`.

## Not in Phase 2

- Webhook default change and parser gating — Phase 3
- Admin membership CRUD and trader search — Phase 4
- Admin frontend chat function selector and team UI — Phase 5
- Dispute notification jobs and reminders — Phases 6–7

`TelegramChatWebhookIngestionService::resolveTelegramChat()` still creates new chats with `parser_type = standard_dispute` until Phase 3.
