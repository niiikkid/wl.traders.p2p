# Step 4 — Backend Category Services

> Source: repository implementation session
> Collected: 2026-05-24
> Published: 2026-05-24

## Summary

Implemented `MerchantTrafficCategoryService` (contract + DTO) and wired it into existing controllers. Step 4 of the implementation plan is complete; API endpoints (steps 5–6) and `FindAvailablePaymentDetail` filtering (step 7) remain pending.

## New files

- `app/Contracts/MerchantTrafficCategoryServiceContract.php`
- `app/Services/TrafficCategory/MerchantTrafficCategoryService.php`
- `app/DTO/TrafficCategory/TrafficCategoryUpsertDTO.php`

## Service access

`services()->merchantTrafficCategory()` via `ServiceBuilder` and `AppServiceProvider` singleton binding.

## Public methods

| Method | Purpose |
|--------|---------|
| `create(TrafficCategoryUpsertDTO)` | Category CRUD create; unique slug; if `enabled_by_default`, upsert `enabled=true` for all `Trader` users (chunk 200) |
| `update(Category, DTO)` | Update fields; does not mass-change existing trader pivots when default flag changes |
| `delete(Category)` | Delete inside transaction (FK cascades on pivots) |
| `syncMerchantCategories(Merchant, array $categoryIds)` | `category_merchant` sync |
| `setTraderCategoryEnabled(User, Category, bool)` | Single pivot toggle + sync legacy JSON |
| `syncTraderAllowedCategoryIds(User, array $categoryIds)` | Legacy trader settings: empty array = enable all categories on pivot; non-empty = per-id enabled state; updates `user_metas.allowed_categories` |
| `applyToAllTraders(Category)` | Mass enable/disable for all traders per category's current `enabled_by_default` (chunked upsert) |
| `initializeDefaultsForTrader(User)` | On new trader: enable categories where `enabled_by_default=true`; sync legacy JSON |

## Controller integration

- `Admin\CategoryController` — store/update/destroy delegate to service
- `Admin\MerchantController::updateSettings` — `syncMerchantCategories()` instead of direct `sync()`
- `Trader\SettingController::update` — `syncTraderAllowedCategoryIds()` for `allowed_categories`
- `UserService::create` — `initializeDefaultsForTrader()` after role assignment

## Transition notes

- `user_metas.allowed_categories` kept in sync when trader category state changes via service.
- Step 7 filtering must use `category_user` pivot, not JSON.
- Admin category resource routes remain commented out; dedicated modal API is step 5.
