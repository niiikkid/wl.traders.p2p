# Merchant Traffic Categories Architecture

> Sources: User conversation, 2026-05-22; repository exploration, 2026-05-22; schema audit, 2026-05-24; schema migrations, 2026-05-24; global setting, 2026-05-24; backend category services, 2026-05-24; admin API endpoints, 2026-05-24; trader API endpoints, 2026-05-24; FindAvailablePaymentDetail filter, 2026-05-24; admin UI, 2026-05-24; trader UI, 2026-05-24
> Raw: [Merchant Traffic Categories Requirements](../../raw/traffic-categories/2026-05-22-merchant-traffic-categories-requirements.md); [Step 1 Schema Confirmation](../../raw/traffic-categories/2026-05-24-step-1-schema-confirmation.md); [Step 2 Schema Migrations](../../raw/traffic-categories/2026-05-24-step-2-schema-migrations.md); [Step 3 Global Setting](../../raw/traffic-categories/2026-05-24-step-3-global-setting.md); [Step 4 Backend Category Services](../../raw/traffic-categories/2026-05-24-step-4-backend-category-services.md); [Step 5 Admin API Endpoints](../../raw/traffic-categories/2026-05-24-step-5-admin-api-endpoints.md); [Step 6 Trader API Endpoints](../../raw/traffic-categories/2026-05-24-step-6-trader-api-endpoints.md); [Step 7 FindAvailablePaymentDetail Filter](../../raw/traffic-categories/2026-05-24-step-7-find-available-payment-detail-filter.md); [Step 8 Admin UI](../../raw/traffic-categories/2026-05-24-step-8-admin-ui.md); [Step 9 Trader UI](../../raw/traffic-categories/2026-05-24-step-9-trader-ui.md)
> Updated: 2026-05-24

## Overview

Merchant traffic categories let administrators mark merchants with one or more traffic groups, and let traders choose which groups they want to receive. The feature is optional at the system level: when disabled, traders do not see category controls and payment detail selection behaves exactly like the old system. Category choices are preserved while disabled, so admin can prepare or pause the feature without losing data.

**Status:** not shipped end-to-end. Implementation plan steps **1–9 complete** (2026-05-24). Admin merchants UI, trader orders UI, and backend filter are in code; **manual verification (steps 10–11)** remains before calling the feature shipped.

## Implementation Status

| Step | Topic | Status |
|------|-------|--------|
| 1 | Schema confirmation | **Done** — [Step 1 — Schema Confirmation](#step-1--schema-confirmation-done-2026-05-24) |
| 2 | Migrations (`enabled_by_default`, `category_user`, required `description`) | **Done** — [Step 2 — Schema Migrations](#step-2--schema-migrations-done-2026-05-24) |
| 3 | `merchant_traffic_categories_enabled` in `SettingService` | **Done** — [Step 3 — Global Setting](#step-3--global-setting-done-2026-05-24) |
| 4 | Backend category services | **Done** — [Step 4 — Backend Category Services](#step-4--backend-category-services-done-2026-05-24) |
| 5 | Admin API endpoints (category modal + merchant assignment) | **Done** — [Step 5 — Admin API Endpoints](#step-5--admin-api-endpoints-done-2026-05-24) |
| 6 | Trader API endpoints (read + per-category toggle) | **Done** — [Step 6 — Trader API Endpoints](#step-6--trader-api-endpoints-done-2026-05-24) |
| 7 | Filtering in `FindAvailablePaymentDetail` | **Done** — [Step 7 — FindAvailablePaymentDetail Filter](#step-7--findavailablepaymentdetail-filter-done-2026-05-24) |
| 8 | Admin UI (merchants page) | **Done** — [Step 8 — Admin UI](#step-8--admin-ui-done-2026-05-24) |
| 9 | Trader UI (orders page) | **Done** — [Step 9 — Trader UI](#step-9--trader-ui-done-2026-05-24) |
| 10–11 | Copy polish and manual verification | Pending (steps 8–9 include spec copy in admin modals and trader row) |
| 12 | Automated tests | Deferred per project rules |

## Product Rules

- Categories filter access to traffic only. They do not calculate trader commission and do not change payout math.
- A merchant can have zero, one, or many categories.
- A trader can enable zero, one, or many categories.
- If the global feature is disabled, categories are ignored everywhere in traffic selection.
- If a merchant has no categories, its traffic is available to all eligible traders.
- If a merchant has categories, a trader is eligible when at least one merchant category is enabled for that trader.
- If a trader disables every category, they can still receive traffic from merchants without categories.
- Category descriptions are required because traders see them in tooltips.
- The system toggle is disabled by default.

## Existing Code Anchors

The repository already has part of the foundation:

- `app/Services/TrafficCategory/MerchantTrafficCategoryService.php` — category CRUD, merchant sync, trader pivot toggles, apply-to-all-traders, new-trader defaults, **`constrainEligibleTradersForMerchant()`** for order traffic (step 7); access via `services()->merchantTrafficCategory()`.
- `app/Contracts/MerchantTrafficCategoryServiceContract.php` — service interface.
- `app/DTO/TrafficCategory/TrafficCategoryUpsertDTO.php` — create/update payload.
- `app/Models/Category.php` — `enabled_by_default` cast; `merchants()` and `traders()` (via `category_user`).
- `app/Models/User.php` — `trafficCategories()` with pivot `enabled`.
- `app/Models/Merchant.php` has a `categories()` many-to-many relation.
- `category_merchant` and **`category_user`** pivot tables exist (migration `2026_05_24_173658_*`).
- `app/Models/UserMeta.php` still has `allowed_categories` JSON (trader settings UI; kept in sync by service during transition).
- `app/Http/Controllers/Admin/MerchantTrafficCategoryController.php` — JSON API for category modal, global toggle, apply-to-all, merchant category sync (step 5).
- `app/Http/Requests/Admin/TrafficCategory/*` — `StoreRequest`, `UpdateRequest`, `UpdateEnabledRequest`, `SyncMerchantCategoriesRequest`.
- Admin routes: prefix `admin/traffic-categories` (`admin.traffic-categories.*`) and `PATCH admin/merchants/{merchant}/categories` (`admin.merchants.categories.update`). Ziggy regenerated after route changes.
- `app/Http/Controllers/Admin/CategoryController.php` — legacy Inertia CRUD; `Route::resource('/categories', …)` still commented out; modal UX should use `MerchantTrafficCategoryController`.
- `app/Http/Controllers/Admin/MerchantController.php` — `updateSettings` still supports `categories` via `syncMerchantCategories()`; dedicated merchant modal should prefer `admin.merchants.categories.update`.
- `app/Http/Controllers/Trader/TrafficCategoryController.php` — JSON API: list categories with trader `enabled` state; PATCH toggle per category (step 6).
- `app/Http/Resources/TraderTrafficCategoryResource.php` — trader API shape (`id`, `name`, `slug`, `description`, `enabled`; no `enabled_by_default`).
- `app/Http/Requests/Trader/TrafficCategory/UpdateCategoryEnabledRequest.php` — `enabled` boolean for PATCH.
- Trader routes: `GET /traffic-categories` (`traffic-categories.index`), `PATCH /traffic-categories/{category}/enabled` (`traffic-categories.enabled.update`); middleware `role:Trader|Super Admin`.
- `app/Http/Controllers/Trader/SettingController.php` — `syncTraderAllowedCategoryIds()` on settings save (legacy Inertia settings; order-page UI uses step 6 API via step 9 composable).
- `app/Services/User/UserService.php` — `initializeDefaultsForTrader()` after new user role assignment.
- `resources/js/composables/useMerchantTrafficCategories.js` — admin fetch/cache for categories list + global flag (step 8).
- `resources/js/Modals/MerchantTrafficCategory/MerchantTrafficCategoryManagerModal.vue` — category CRUD, global toggle, apply-to-all (step 8).
- `resources/js/Modals/MerchantTrafficCategory/MerchantTrafficCategoriesAssignModal.vue` — merchant `category_ids` sync (step 8).
- `resources/js/store/modal.js` — `merchantTrafficCategoryManager`, `merchantTrafficCategoriesAssign` modal keys (step 8).
- `resources/js/Pages/Merchant/Index.vue` — admin **Категории** button, badges column, assign action; hosts step 8 modals + `ConfirmModal` in admin view.
- `app/Http/Resources/MerchantResource.php` — `traffic_categories` (`id`, `name`) for table badges; `categories` remains merchant category ID array for settings.
- `app/Http/Controllers/Admin/MerchantController.php` — `index` / `indexData` eager-load `categories` (step 8).
- `resources/js/Modals/Merchant/MerchantSettingsModal.vue` and `resources/js/Pages/Merchant/Tabs/Settings.vue` — legacy merchant settings (can still sync categories via `updateSettings`; prefer assign modal + `admin.merchants.categories.update`).
- `resources/js/composables/useTraderTrafficCategories.js` — trader fetch + per-category PATCH (step 9).
- `resources/js/Components/Order/TraderTrafficCategoriesRow.vue` — compact category row, tooltips, optimistic toggle (step 9).
- `resources/js/Pages/Order/Index.vue` — trader orders page; hosts `TraderTrafficCategoriesRow` in `MainTableSection` `#header` when `isTraderViewMode` (step 9).
- `resources/js/Components/AppTooltip.vue` — optional `showDelayMs` for delayed tooltips (400 ms on category buttons, step 9).
- `app/Services/Order/Features/OrderDetailProvider/Classes/FindAvailablePaymentDetail.php` — calls `constrainEligibleTradersForMerchant()` on the online-trader query in `queryPaymentDetails()` (step 7).
- `app/Services/Settings/SettingsService.php` — `isMerchantTrafficCategoriesEnabled()` / `updateMerchantTrafficCategoriesEnabled()`; key `merchant_traffic_categories_enabled`, default `false`; install via `php artisan app:install-settings`.

## Data Model

### Categories

**Current** (step 2, live DB):

- `id`
- `name`, `slug` (unique, auto from name in `CategoryController`)
- `description`, **required** `text` (NOT NULL; admin validation `required|string`)
- `enabled_by_default`, `boolean`, default `false`
- `created_at`, `updated_at`

Historical step 1 audit: [Step 1 — Schema Confirmation](#step-1--schema-confirmation-done-2026-05-24).

Do not store commission percentage as a required field in the first version. Percent-based categories are only one use case.

### Merchant Category Assignments

Use the existing many-to-many pivot:

- `category_merchant`
- `merchant_id`
- `category_id`

When a category is deleted, detach it from merchants. If a merchant has no remaining categories, it becomes unrestricted by categories.

### Trader Category Choices

**Current** (step 2): normalized pivot **`category_user`**:

- `category_id`, `user_id`, `enabled` (default `true`), timestamps
- Unique `(category_id, user_id)`; index `(user_id, enabled)`
- Eloquent: `User::trafficCategories()`, `Category::traders()` with `withPivot('enabled')`
- Backfill from `user_metas.allowed_categories` on migration (non-empty JSON → `enabled = true`)

**Transition:** `user_metas.allowed_categories` remains for `Trader\SettingController` + `Settings/Trader/Index.vue`. Step 7 filtering uses **`category_user`** only; later steps may sync or retire JSON.

### Global Feature Setting

**Current** (step 3):

| Key | Value | Default | Cache |
|-----|-------|---------|-------|
| `merchant_traffic_categories_enabled` | `0` / `1` | `0` (disabled) | `settings_merchant_traffic_categories_enabled`, TTL ~1 min |

- `SettingsService::isMerchantTrafficCategoriesEnabled(): bool`
- `SettingsService::updateMerchantTrafficCategoriesEnabled(bool $enabled): void`
- Seeded in `createAll()`; install with `php artisan app:install-settings`
- Pattern matches `isTrafficPaused()` / `updateTrafficPaused()`

If the setting cannot be read because of an unexpected system problem, fail closed for categorized traffic: do not silently ignore category restrictions. Log the exception clearly. This avoids accidentally sending restricted merchant traffic to traders who did not opt in. **Implemented (step 7)** in `MerchantTrafficCategoryService::resolveMerchantTrafficCategoriesEnabled()` — categorized merchants get `whereRaw('0 = 1')` on the trader query; uncategorized merchants are unaffected. Intentional disable (`value = 0` or missing row) still returns `false` from the getter without throwing.

## Admin UX

**Current (step 8, shipped in code).** Implemented on `resources/js/Pages/Merchant/Index.vue` when `viewStore.isAdminViewMode`. See [Step 8 — Admin UI](#step-8--admin-ui-done-2026-05-24).

### Merchant Page Entry Point

On the admin merchant list (`/admin/merchants`), compact button in the table header (`MainTableSection` `#button` slot):

- Label: `Категории`
- Opens the category management modal.
- The modal should show a clear status badge:
  - `Категории выключены` when global filtering is off.
  - `Категории включены` when global filtering is on.

Plain-language explanation:

> Когда категории выключены, они не влияют на раздачу трафика. Трейдеры их не видят. Можно спокойно настроить категории заранее и включить позже.

### Category Management Modal

The modal should support:

- Create category.
- Edit category.
- Delete category.
- Toggle global feature state.
- Apply a category default to all traders.

Fields:

- `Название` — short visible name.
- `Описание` — required explanation shown to traders on hover.
- `Включать новым трейдерам по умолчанию` — default state for future traders.

Important copy near the default toggle:

> Это влияет только на новых трейдеров. Уже выбранные настройки трейдеров не меняются.

Explicit mass action:

> Применить ко всем трейдерам

Confirmation text should explain:

> Если категория включена по умолчанию, она включится всем трейдерам. Если выключена — выключится всем трейдерам.

### Merchant Category Assignment

Add a merchant action in the existing settings/dropdown area:

- Label: `Категории`
- Opens a small modal with multi-select category assignment.
- Admin can assign one or more categories.
- Admin can clear all categories.

Show this explanation in the modal:

> Если у мерчанта нет категорий, его заявки доступны всем трейдерам без фильтрации по категориям.

### Merchant Table Badges

Add a `Категории` column to the admin merchant table:

- Show assigned categories as compact badges.
- If no categories: show a muted badge like `Без категорий`.
- If global feature is disabled, still show badges, but make the disabled system state visible near the page action or modal entry.

## Trader UX

**Current (step 9, shipped in code).** See [Step 9 — Trader UI](#step-9--trader-ui-done-2026-05-24).

Show the category row only when `merchant_traffic_categories_enabled` is true and at least one category exists (component hides otherwise).

Placed above the orders filters in `resources/js/Pages/Order/Index.vue` → `MainTableSection` `#header` slot (before `FiltersPanel`).

The row should be full width but compact:

- Small title: `Категории трафика`
- Short explanation:

> Включите категории, с которыми хотите работать. Если выключить категорию, заявки от таких мерчантов не будут приходить.

- Category buttons:
  - Enabled state should look active and confident.
  - Disabled state should look muted.
  - Every button should indicate hover help, for example with a small info icon or dotted underline.
  - Tooltip appears after a **400 ms** delay (`AppTooltip` `showDelayMs`) and shows the category description.

Interaction:

- Clicking a category toggles the trader's enabled state.
- Save immediately via `PATCH traffic-categories/{category}/enabled` with `{ "enabled": true|false }` (step 6 API).
- Optimistic UI with rollback on failure; `AlertError` shows the error message.

If the feature is disabled or the category list is empty, render nothing for traders. This keeps the product behaving as if the feature does not exist.

## Backend Filtering

**Current (step 7, shipped in code).** The filtering belongs in the payment detail search path, not only in UI:

`OrderPoolingService` -> `OrderPoolingJob` -> `OrderService::create` -> `OrderDetailAssigner::assign` -> `OrderDetailProvider::provide` -> `FindAvailablePaymentDetail::queryPaymentDetails()`

Filtering algorithm (implemented via `MerchantTrafficCategoryService::constrainEligibleTradersForMerchant()` on the online-trader `User` query, before wallet/dispute filters):

1. Resolve the order merchant (constructor `$this->merchant`).
2. Read the global feature flag from `SettingService` (try/catch for fail-closed).
3. If disabled, do not add category filters.
4. Load merchant category IDs from `category_merchant`.
5. If the merchant has no categories, do not add category filters.
6. If the merchant has categories, restrict eligible traders with `whereHas('trafficCategories', …)` where `category_user.enabled = true` and category ID intersects merchant assignments.
7. On setting read failure with categorized merchant: `whereRaw('0 = 1')` + `Log::error`.
8. Continue applying the existing filters: wallet, online state, traffic pause, gateway, limits, method, `active()`, `availableBySchedule()`, and other eligibility rules.

The logic is applied before a payment detail is selected. UI controls are not security or business enforcement.

## Default Application

### Creating A Category

When admin creates a category:

- Store the category.
- If `enabled_by_default` is true, attach or enable the category for all existing traders.
- If false, do not enable it for existing traders.

### Creating A Trader

When a new trader/user meta is created:

- Enable all categories where `enabled_by_default = true`.
- Leave other categories disabled.

This can live in the user/trader creation flow, a service, or an observer if the project already uses that style.

### Editing Default Flag

Editing `enabled_by_default` affects only future traders. Existing trader choices are not changed automatically.

The admin UI must say this clearly and provide the separate `Применить ко всем трейдерам` button for mass changes.

### Applying To All Traders

The explicit mass action uses the current category default flag:

- If `enabled_by_default = true`, enable this category for all traders.
- If `enabled_by_default = false`, disable this category for all traders.

Use a transaction or chunked update strategy so the operation is reliable on a large trader base.

## Deletion Behavior

Deleting a category should:

- Detach it from merchants.
- Remove or disable it for traders.
- Leave all other category assignments intact.
- Make any merchant with no remaining categories unrestricted by category filtering.

Use a confirmation modal with simple copy:

> Категория удалится у мерчантов и трейдеров. Если у мерчанта не останется категорий, его заявки снова будут доступны всем трейдерам.

## Failure And Safety

The feature has two different safety modes:

- Feature disabled intentionally: ignore categories and behave like the old system.
- Feature broken unexpectedly: do not silently bypass restrictions for categorized merchants; log the error and fail the assignment path rather than sending restricted traffic incorrectly.

This distinction is important. "Disabled" is a normal business state. "Broken" is an operational incident.

## Step 1 — Schema Confirmation (Done 2026-05-24)

Verified against migrations, models, controllers, routes, and live DB (`php artisan db:table`).

### `categories`

| Column | Status | Notes |
|--------|--------|-------|
| `id` | Present | PK |
| `name` | Present | `string`, required in admin validation |
| `slug` | Present | unique, auto from name in `CategoryController` |
| `description` | **Present, nullable** | `text`, nullable in DB; validation `nullable\|string` in `CategoryController`; product spec requires required text for trader tooltips — tighten in step 2 |
| `enabled_by_default` | **Missing** | Not in migration `2025_03_11_010939_create_categories_table.php`, model, resource, or UI |
| `created_at` / `updated_at` | Present | |

Data snapshot: **0** category rows; no backfill risk for new columns or `description` NOT NULL.

### `category_merchant`

Present and matches the plan: `category_id`, `merchant_id`, unique pair, cascade on delete. Used by `Merchant::categories()` and `Admin\MerchantController` sync.

### Trader category storage

| Mechanism | Status | Notes |
|-----------|--------|-------|
| `user_metas.allowed_categories` | Present | JSON array; trader settings at `Trader\SettingController` + `Settings/Trader/Index.vue` |
| `category_user` pivot | **Missing** | Wiki prefers normalized pivot for query performance; open decision for step 2 |

### Global feature flag

`merchant_traffic_categories_enabled` — **not** in `SettingsService` yet (step 3).

### Traffic filtering

`FindAvailablePaymentDetail` — **done (step 7)** — `constrainEligibleTradersForMerchant()` on trader query; uses `category_user`, not JSON.

### Admin category UI routes

`routes/web.php` — `Route::resource('/categories', CategoryController::class)` is **commented out**. Pages exist under `resources/js/Pages/Category/*`; merchant assignment already works via `Admin\MerchantController` + settings modal.

### Step 1 answers

- **`description`**: yes, column exists; nullable and optional in validation — not yet aligned with “required for traders”.
- **`enabled_by_default`**: no — add in step 2.

## Step 2 — Schema Migrations (Done 2026-05-24)

Migration: `database/migrations/2026_05_24_173658_add_merchant_traffic_category_schema_to_categories_table.php` (applied).

### `categories` (post-migration)

| Column | Status | Notes |
|--------|--------|-------|
| `description` | **NOT NULL** | Existing NULLs set to `''` before alter |
| `enabled_by_default` | **Present** | `boolean`, default `false` |

### `category_user`

| Column | Notes |
|--------|-------|
| `category_id`, `user_id` | FK cascade on delete |
| `enabled` | Default `true` |
| Unique | `(category_id, user_id)` |
| Index | `(user_id, enabled)` |

Backfill: `user_metas.allowed_categories` → `category_user` (`enabled = true`, `insertOrIgnore`, valid category ids only).

### Code touched in step 2

| Area | Change |
|------|--------|
| `Category` model | `enabled_by_default` cast; `traders()` relation |
| `User` model | `trafficCategories()` relation |
| `CategoryController` | `description` required; `enabled_by_default` boolean |
| `CategoryResource` | `enabled_by_default` in API shape |
| `Category/Create`, `Edit`, `Index.vue` | Default-flag checkbox and table column |

### Still pending after step 2

- ~~Category services, endpoints, `FindAvailablePaymentDetail` filter (steps 4–7)~~ **Done**
- ~~Admin merchants page modal (step 8)~~ **Done**
- ~~Trader order-toolbar UI (step 9)~~ **Done**

## Step 3 — Global Setting (Done 2026-05-24)

Added global feature toggle to `SettingsService` and `SettingsServiceContract`.

### Setting row

| Key | Default | Notes |
|-----|---------|-------|
| `merchant_traffic_categories_enabled` | `0` | Boolean stored as int; disabled by default |

### Code touched in step 3

| Area | Change |
|------|--------|
| `SettingsService` | Constants, `isMerchantTrafficCategoriesEnabled()`, `updateMerchantTrafficCategoriesEnabled()`, `createAll()` seed |
| `SettingsServiceContract` | Getter and updater on interface |

### Install

```bash
php artisan app:install-settings
```

### Still pending after step 3

- ~~Category services, endpoints, `FindAvailablePaymentDetail` filter (steps 4–7)~~ **Done**
- ~~Admin merchants UI (step 8)~~ **Done** — global toggle in manager modal
- ~~Trader order-toolbar UI (step 9)~~ **Done**

## Step 4 — Backend Category Services (Done 2026-05-24)

Centralized domain logic in `MerchantTrafficCategoryService` with contract binding in `AppServiceProvider` and `ServiceBuilder::merchantTrafficCategory()`.

### Service responsibilities

| Area | Implementation |
|------|----------------|
| Category CRUD | `create()`, `update()`, `delete()` with `TrafficCategoryUpsertDTO`; unique `slug` on name collision |
| Create + default flag | If `enabled_by_default`, upsert `category_user` with `enabled=true` for all `Trader` users (chunk 200) |
| Merchant assignment | `syncMerchantCategories()` wraps `category_merchant` sync |
| Trader toggle | `setTraderCategoryEnabled()` — single pivot update + legacy JSON sync |
| Legacy trader settings | `syncTraderAllowedCategoryIds()` — empty `allowed_categories` means all categories enabled on pivot (old UI semantics); non-empty sets per-category `enabled` and updates JSON |
| Apply to all traders | `applyToAllTraders()` — mass state from category's current `enabled_by_default` (chunked upsert) |
| New trader | `initializeDefaultsForTrader()` — called from `UserService::create` when role is `Trader`; enables categories with `enabled_by_default=true` |
| Order traffic filter | `constrainEligibleTradersForMerchant()` — added in step 7; see [Step 7 — FindAvailablePaymentDetail Filter](#step-7--findavailablepaymentdetail-filter-done-2026-05-24) |

### Code touched in step 4

| Area | Change |
|------|--------|
| `MerchantTrafficCategoryService` | New service class |
| `MerchantTrafficCategoryServiceContract` | Interface |
| `TrafficCategoryUpsertDTO` | Create/update DTO |
| `ServiceBuilder` / `ServiceBuilderContract` | `merchantTrafficCategory()` accessor |
| `AppServiceProvider` | Singleton binding |
| `CategoryController` | Delegates store/update/destroy |
| `MerchantController` | `syncMerchantCategories()` in settings update |
| `SettingController` | `syncTraderAllowedCategoryIds()` |
| `UserService` | `initializeDefaultsForTrader()` on create |

### Still pending after step 4

- ~~Dedicated admin/trader API endpoints for modal UX (steps 5–6)~~ **Done**
- ~~`FindAvailablePaymentDetail` category filter and fail-closed setting read (step 7)~~ **Done**
- ~~Admin merchants UI (step 8)~~ **Done**
- ~~Trader order-toolbar UI (step 9)~~ **Done**

## Step 5 — Admin API Endpoints (Done 2026-05-24)

JSON admin API for the merchants-page category modal and merchant assignment modal. Response envelope: `{ "success": true, "data": ... }` (same pattern as `PaymentDetailScheduleController`).

### Routes

| Method | Path | Route name | Purpose |
|--------|------|------------|---------|
| GET | `/admin/traffic-categories` | `admin.traffic-categories.index` | Categories list + `merchant_traffic_categories_enabled` |
| POST | `/admin/traffic-categories` | `admin.traffic-categories.store` | Create |
| PATCH | `/admin/traffic-categories/settings/enabled` | `admin.traffic-categories.settings.enabled.update` | Global toggle |
| PATCH | `/admin/traffic-categories/{category}` | `admin.traffic-categories.update` | Update |
| DELETE | `/admin/traffic-categories/{category}` | `admin.traffic-categories.destroy` | Delete |
| POST | `/admin/traffic-categories/{category}/apply-to-all-traders` | `admin.traffic-categories.apply-to-all-traders` | Mass apply per `enabled_by_default` |
| PATCH | `/admin/merchants/{merchant}/categories` | `admin.merchants.categories.update` | Sync `category_ids` on merchant |

Middleware: `auth`, `banned`, `role:Super Admin`. `settings/enabled` is registered before `{category}` to avoid route shadowing.

### Validation (Form requests)

| Request | Fields |
|---------|--------|
| Store / Update | `name` required; `description` required; `enabled_by_default` optional boolean |
| UpdateEnabled | `enabled` required boolean |
| SyncMerchantCategories | `category_ids` present array; each id `exists:categories,id` |

### Key payloads

- **Index `data`:** `merchant_traffic_categories_enabled`, `categories` (`CategoryResource` resolved).
- **Merchant sync body:** `{ "category_ids": [1, 2] }` — empty array clears all (merchant unrestricted).
- **Apply to all:** no body; calls `MerchantTrafficCategoryService::applyToAllTraders()`.

### Code touched in step 5

| Area | Change |
|------|--------|
| `MerchantTrafficCategoryController` | New admin JSON controller |
| `app/Http/Requests/Admin/TrafficCategory/*` | Four form requests |
| `TrafficCategoryUpsertDTO` | `makeFromRequest()` |
| `routes/web.php` | Routes under `traffic-categories` + `merchants/{merchant}/categories` |

### Still pending after step 5

- ~~Trader API for order-page category toggles (step 6)~~ **Done**
- ~~`FindAvailablePaymentDetail` category filter and fail-closed setting read (step 7)~~ **Done**
- ~~Admin UI wired to these endpoints (step 8)~~ **Done**
- ~~Trader UI (step 9)~~ **Done**

## Step 6 — Trader API Endpoints (Done 2026-05-24)

JSON trader API for the order-page category row (consumed by step 9 UI). Response envelope: `{ "success": true, "data": ... }` (same pattern as admin step 5 and `PaymentDetailScheduleController`).

### Routes

| Method | Path | Route name | Purpose |
|--------|------|------------|---------|
| GET | `/traffic-categories` | `traffic-categories.index` | Categories list + `merchant_traffic_categories_enabled` |
| PATCH | `/traffic-categories/{category}/enabled` | `traffic-categories.enabled.update` | Toggle `enabled` for current trader |

Middleware: `auth`, `banned`, `role:Trader|Super Admin` (trader route group with payment-detail-schedules). `ensureTrader()` via `isRouteFor('Trader')`.

### Validation (Form request)

| Request | Fields |
|---------|--------|
| UpdateCategoryEnabled | `enabled` required boolean |

### Key payloads

- **Index when global off:** `merchant_traffic_categories_enabled: false`, `categories: []`.
- **Index when global on:** `merchant_traffic_categories_enabled: true`, `categories` — `TraderTrafficCategoryResource` (resolved); `enabled` from `category_user` pivot, default `false` if no pivot row.
- **PATCH body:** `{ "enabled": true }` — calls `MerchantTrafficCategoryService::setTraderCategoryEnabled()` (pivot + legacy JSON sync).
- **PATCH when global off:** HTTP 403 (`Категории трафика отключены.`).

### Code touched in step 6

| Area | Change |
|------|--------|
| `Trader\TrafficCategoryController` | New trader JSON controller |
| `TraderTrafficCategoryResource` | Trader-facing category resource |
| `app/Http/Requests/Trader/TrafficCategory/UpdateCategoryEnabledRequest` | PATCH validation |
| `routes/web.php` | `traffic-categories.*` in trader middleware group |

### Still pending after step 6

- ~~`FindAvailablePaymentDetail` category filter and fail-closed setting read (step 7)~~ **Done**
- ~~Admin UI wired to admin endpoints (step 8)~~ **Done**
- ~~Trader UI wired to trader endpoints (step 9)~~ **Done** — see [Step 9 — Trader UI](#step-9--trader-ui-done-2026-05-24)

## Step 7 — FindAvailablePaymentDetail Filter (Done 2026-05-24)

Enforces merchant traffic category rules in the payment-detail assignment path when the global flag is on.

### Call site

`FindAvailablePaymentDetail::queryPaymentDetails()` — on the online-trader `User::query()`, after `is_online` / `stop_traffic` / ban checks, **before** wallet and dispute filters:

```php
->tap(fn (Builder $query) => services()->merchantTrafficCategory()->constrainEligibleTradersForMerchant($query, $this->merchant))
```

### Service method

`MerchantTrafficCategoryService::constrainEligibleTradersForMerchant(Builder $userQuery, Merchant $merchant): void`

| Condition | Effect on `$userQuery` |
|-----------|------------------------|
| Global flag off | No change |
| Merchant has no `category_merchant` rows | No change |
| Global on + merchant has categories | `whereHas('trafficCategories', …)` with `category_user.enabled = true` and `categories.id` in merchant set |
| `isMerchantTrafficCategoriesEnabled()` throws + merchant has categories | `whereRaw('0 = 1')` + `Log::error` (fail closed) |
| Setting throws + merchant has no categories | No change |

Pivot **`category_user` only** — not `user_metas.allowed_categories`.

### Private helpers (step 7)

| Method | Role |
|--------|------|
| `merchantCategoryIds()` | `merchant->categories()->pluck('categories.id')` |
| `resolveMerchantTrafficCategoriesEnabled()` | try/catch around settings getter; `null` signals fail-closed for categorized merchants |

### Code touched in step 7

| Area | Change |
|------|--------|
| `MerchantTrafficCategoryService` | `constrainEligibleTradersForMerchant()`, helpers |
| `MerchantTrafficCategoryServiceContract` | Interface method |
| `FindAvailablePaymentDetail` | `->tap(… constrainEligibleTradersForMerchant …)` |

### Still pending after step 7

- ~~Admin UI (step 8)~~ **Done** — see [Step 8 — Admin UI](#step-8--admin-ui-done-2026-05-24)
- ~~Trader UI (step 9)~~ **Done** — see [Step 9 — Trader UI](#step-9--trader-ui-done-2026-05-24)
- Copy and manual verification (steps 10–11)

## Step 8 — Admin UI (Done 2026-05-24)

Admin merchants UI wired to step 5 JSON API. Pattern: modal-first (like `PaymentDetailScheduleManagerModal`), axios + `{ success, data }` envelope.

### Entry points (`Merchant/Index.vue`)

| UI | Action |
|----|--------|
| Header button **Категории** | Opens `MerchantTrafficCategoryManagerModal` |
| Column **Категории** | Badges from `merchant.traffic_categories`; **Без категорий** when empty |
| Row menu **Категории** | Opens `MerchantTrafficCategoriesAssignModal` for that merchant |
| Row menu **Настройки** | Existing `MerchantSettingsModal` (unchanged) |

Modals render on the merchants page only (admin view), with `ConfirmModal` for delete and apply-to-all.

### Composable

`useMerchantTrafficCategories()`:

- `GET admin.traffic-categories.index` → `categories`, `merchant_traffic_categories_enabled`
- `invalidateCategories()` after mutations
- `categoryOptions()` for assign multiselect (`id`, `name`)

### Manager modal (`MerchantTrafficCategoryManagerModal.vue`)

| Action | Route |
|--------|-------|
| List + global flag | `admin.traffic-categories.index` |
| Global toggle | `PATCH admin.traffic-categories.settings.enabled.update` |
| Create | `POST admin.traffic-categories.store` |
| Update | `PATCH admin.traffic-categories.update` |
| Delete | `DELETE admin.traffic-categories.destroy` |
| Apply to all traders | `POST admin.traffic-categories.apply-to-all-traders` |

UI: global status badge + toggle bar; left category list; right editor (`name`, `description`, `enabled_by_default`); spec copy for disabled state, new-trader default, apply-to-all, delete confirmation.

### Assign modal (`MerchantTrafficCategoriesAssignModal.vue`)

| Action | Route |
|--------|-------|
| Load options | `admin.traffic-categories.index` (via composable) |
| Save | `PATCH admin.merchants.categories.update` — `{ category_ids: number[] }` |

`Multiselect` (`value-key="id"`). Empty selection clears all categories (merchant unrestricted). On success: `onUpdated` callback → `fetchMerchants()`.

### API resource tweak

`MerchantResource`:

- `categories` — array of category IDs (settings / legacy forms)
- `traffic_categories` — `[{ id, name }, …]` when `categories` relation loaded (table badges)

`Admin\MerchantController::index` and `indexData` eager-load `categories`.

### Code touched in step 8

| Area | Change |
|------|--------|
| `useMerchantTrafficCategories.js` | New composable |
| `MerchantTrafficCategoryManagerModal.vue` | New modal |
| `MerchantTrafficCategoriesAssignModal.vue` | New modal |
| `modal.js` | Two modal registrations + open helpers |
| `Merchant/Index.vue` | Button, column, actions, modal hosts |
| `MerchantResource` | `traffic_categories` |
| `Admin\MerchantController` | Eager-load `categories` on list |

### Still pending after step 8

- ~~Trader UI (step 9): category row on `Order/Index.vue`~~ **Done** — see [Step 9 — Trader UI](#step-9--trader-ui-done-2026-05-24)
- Browser verification (steps 10–11)
- Automated tests (step 12, deferred)

## Step 9 — Trader UI (Done 2026-05-24)

Trader orders UI wired to step 6 JSON API. Pattern: composable + inline row component (not modal), axios + `{ success, data }` envelope.

### Placement (`Order/Index.vue`)

| UI | Location |
|----|----------|
| `TraderTrafficCategoriesRow` | `MainTableSection` `#header`, first child in `space-y-4`, only when `viewStore.isTraderViewMode` |
| Filters / table | Unchanged below the row |

### Composable (`useTraderTrafficCategories.js`)

| Method | Route |
|--------|-------|
| `fetchCategories(force?)` | `GET traffic-categories.index` |
| `setCategoryEnabled(id, enabled)` | `PATCH traffic-categories.enabled.update` |

Caches list in module-level refs (`categories`, `merchant_traffic_categories_enabled`, `loaded`).

### Row component (`TraderTrafficCategoriesRow.vue`)

| Behavior | Detail |
|----------|--------|
| Visibility | After fetch: show only if global on **and** `categories.length > 0` |
| Loading | Brief skeleton row while first fetch runs |
| Toggle | Optimistic `enabled` flip; rollback + `AlertError` on PATCH failure |
| Busy state | Per-category spinner; button disabled while PATCH in flight |
| Tooltip | `AppTooltip` with `showDelayMs={400}`, `tip={description}` |
| Button styles | Enabled: `btn-primary`; disabled: muted `btn-ghost` + dotted underline on name |
| Copy | Spec title and explanation text (Russian UI) |

### Shared tweak

`AppTooltip.vue` — new optional prop `showDelayMs` (default `0`); step 9 uses `400` for category descriptions.

### Code touched in step 9

| Area | Change |
|------|--------|
| `useTraderTrafficCategories.js` | New composable |
| `TraderTrafficCategoriesRow.vue` | New component |
| `Order/Index.vue` | Import + conditional row in header slot |
| `AppTooltip.vue` | `showDelayMs` delayed show on hover |

### Still pending after step 9

- Copy polish and browser verification (steps 10–11)
- Automated tests (step 12, deferred)

## Implementation Plan

1. ~~Confirm the current category schema and whether `description` and `enabled_by_default` already exist.~~ **Done (2026-05-24)** — see [Step 1 — Schema Confirmation](#step-1--schema-confirmation-done-2026-05-24).
2. ~~Add or adjust migrations for required category fields and normalized trader/category pivot.~~ **Done (2026-05-24)** — see [Step 2 — Schema Migrations](#step-2--schema-migrations-done-2026-05-24).
3. ~~Add the `merchant_traffic_categories_enabled` setting via `SettingService`; install it with the project settings command.~~ **Done (2026-05-24)** — see [Step 3 — Global Setting](#step-3--global-setting-done-2026-05-24).
4. ~~Build backend category services:~~ **Done (2026-05-24)** — see [Step 4 — Backend Category Services](#step-4--backend-category-services-done-2026-05-24).
5. ~~Expose admin endpoints for the category modal and merchant assignment modal.~~ **Done (2026-05-24)** — see [Step 5 — Admin API Endpoints](#step-5--admin-api-endpoints-done-2026-05-24).
6. ~~Expose trader endpoints for reading available categories and toggling enabled categories.~~ **Done (2026-05-24)** — see [Step 6 — Trader API Endpoints](#step-6--trader-api-endpoints-done-2026-05-24).
7. ~~Add backend filtering in `FindAvailablePaymentDetail::queryPaymentDetails()`.~~ **Done (2026-05-24)** — see [Step 7 — FindAvailablePaymentDetail Filter](#step-7--findavailablepaymentdetail-filter-done-2026-05-24).
8. ~~Add admin UI:~~ **Done (2026-05-24)** — see [Step 8 — Admin UI](#step-8--admin-ui-done-2026-05-24).
   - ~~`Категории` button~~
   - ~~category management modal~~
   - ~~global toggle~~
   - ~~category badges column~~
   - ~~merchant category assignment modal~~
9. ~~Add trader UI:~~ **Done (2026-05-24)** — see [Step 9 — Trader UI](#step-9--trader-ui-done-2026-05-24).
   - ~~compact category row above the orders table~~
   - ~~delayed tooltips~~
   - ~~clear enabled/disabled states~~
10. Add clear explanatory copy in every risky place:
    - global disabled state
    - no categories on merchant
    - default flag only affects new traders
    - apply-to-all behavior
11. Manually verify main flows in browser.
12. Add and run tests only if explicitly requested later, according to project rules.

## Open Technical Decisions

- **Decided (step 2):** keep `user_metas.allowed_categories` and `category_user` during transition; filtering and new trader flows should use the pivot; JSON may be retired later.
- Exact permission gate for the admin category UI if there are multiple admin levels.
- **Decided (step 5):** modal-first admin UX uses `MerchantTrafficCategoryController` JSON API (`admin.traffic-categories.*`); legacy `CategoryController` Inertia pages remain but resource routes stay commented out.
- **Decided (step 6):** trader toggles save immediately per category via `PATCH traffic-categories/{category}/enabled`; order-page UI (step 9) does not batch-save through legacy settings.
- **Decided (step 7):** traffic enforcement on `User` query via `constrainEligibleTradersForMerchant()`; fail-closed blocks all traders when setting read throws and merchant has categories; intentional global off leaves behavior unchanged.
- **Decided (step 8):** admin UX lives on `Merchant/Index.vue` only (not `AuthenticatedLayout`); modals call `admin.traffic-categories.*` and `admin.merchants.categories.update`; merchant table uses `traffic_categories` for display and `categories` ID array for settings compatibility.
- **Decided (step 9):** trader UX is `TraderTrafficCategoriesRow` in `Order/Index.vue` `#header` (trader view only); uses `useTraderTrafficCategories` + step 6 routes; hides when global off or no categories; `AppTooltip` delay 400 ms; optimistic toggle with rollback.

## See Also

- [Payment Detail Work Schedule Implementation Plan](../payment-detail-schedules/payment-detail-work-schedule-implementation-plan.md) — also filters traffic via `FindAvailablePaymentDetail` (schedules shipped; categories independent)
- [Telegram Chat Dispute Automation Plan](../telegram/telegram-chat-dispute-automation-plan.md)
