# Local Webhook Base URL for Development

> Source: Implementation in p2p.cti repository (conversation + code changes)
> Collected: 2026-05-21
> Published: 2026-05-21

## Problem

In `local` environment (`is_local()` → `app()->environment('local')`), `TelegramChatBotService::webhookUrl()` used `route(..., absolute: true)`, which resolved to the default app host (e.g. `http://p2p.cti.test/telegram/chat-automation/webhook`). Telegram cannot reach that URL; developers use tunnels (Expose, ngrok, etc.) with a public HTTPS domain.

## Solution

Store an optional `local_webhook_base_url` on `telegram_bot_settings`. When `is_local()` and the field is set, webhook URL is built as:

`{local_webhook_base_url}` + `{relative route path}`

Example: `https://p2p-cti.eu-1.sharedwithexpose.com` + `/telegram/chat-automation/webhook` → `https://p2p-cti.eu-1.sharedwithexpose.com/telegram/chat-automation/webhook`

Empty or null field falls back to the default absolute route URL.

## Database

Migration: `2026_05_21_153227_add_local_webhook_base_url_to_telegram_bot_settings_table.php`

- Column: `local_webhook_base_url`, `string(512)`, nullable, after `webhook_secret`

## Backend

- `TelegramBotSetting` — `local_webhook_base_url` in `$fillable`
- `TelegramChatBotService::updateSettings()` — accepts `localWebhookBaseUrl` and `updateLocalWebhookBaseUrl`; updates field only when `is_local()` and request includes the key; normalizes via `normalizeLocalWebhookBaseUrl()` (trim, strip trailing slash, empty → null)
- `TelegramChatBotService::webhookUrl()` — uses custom base when `is_local()` and stored URL present; path from `route('telegram.chat-automation.webhook', [], false)`
- `UpdateRequest` — `local_webhook_base_url`: nullable, string, max 512, url (rules only when `is_local()`)
- `TelegramBotSettingController::update()` — passes field when `is_local() && $request->has('local_webhook_base_url')`
- `TelegramBotSettingResource` — exposes `is_local`, `local_webhook_base_url` (null outside local), computed `webhook_url`

## Frontend

`resources/js/Pages/Admin/TelegramChats/Index.vue` — bot settings modal:

- Fieldset «Домен webhook (локальная среда)» when `botSettingState.is_local`
- Input `type="url"`, placeholder `https://p2p-cti.eu-1.sharedwithexpose.com`
- Saved via PATCH `admin.telegram-bot.settings.update` as `local_webhook_base_url`
- Help text: public tunnel URL without path; empty = default app domain

After changing the domain, admin must run «Установить webhook» again so Telegram receives the new URL.

## Non-local

Field is not validated, not saved, and not shown when not `is_local()`.
