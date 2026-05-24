# Step 3 Global Setting — Merchant Traffic Categories

> Source: Repository implementation (`app/Services/Settings/SettingsService.php`, `app/Contracts/SettingsServiceContract.php`)
> Collected: 2026-05-24
> Published: Unknown

## Purpose

Implementation plan step 3: add global feature toggle `merchant_traffic_categories_enabled` to `SettingsService`, install via `php artisan app:install-settings`.

## Setting

| Key | Value | Default | Cache key |
|-----|-------|---------|-----------|
| `merchant_traffic_categories_enabled` | `0` / `1` | `0` (disabled) | `settings_merchant_traffic_categories_enabled`, TTL ~1 min |

## API

- `SettingsService::isMerchantTrafficCategoriesEnabled(): bool`
- `SettingsService::updateMerchantTrafficCategoriesEnabled(bool $enabled): void`
- Same methods on `SettingsServiceContract`

Pattern matches `isTrafficPaused()` / `updateTrafficPaused()`: cached read, `updateParam` write, `createAll()` seeds default.

## Install

```bash
php artisan app:install-settings
```

Verified: default returns `disabled`.

## Still pending

- Category services (step 4)
- Admin/trader endpoints (steps 5–6)
- `FindAvailablePaymentDetail` filter (step 7)
- Target admin/trader UI (steps 8–9)
