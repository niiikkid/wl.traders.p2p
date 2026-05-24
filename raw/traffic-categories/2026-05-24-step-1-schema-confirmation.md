# Step 1 Schema Confirmation — Merchant Traffic Categories

> Source: Repository audit (`php artisan db:table`, migrations, models, controllers, routes)
> Collected: 2026-05-24
> Published: Unknown

## Purpose

Implementation plan step 1: confirm the current category schema and whether `description` and `enabled_by_default` already exist.

## `categories` table (live DB)

Columns present:

- `id` — bigint unsigned, PK
- `name` — varchar(255)
- `slug` — varchar(255), unique
- `description` — text, **nullable**
- `created_at`, `updated_at` — timestamp, nullable

Columns **not** present:

- `enabled_by_default`

Migration source: `database/migrations/2025_03_11_010939_create_categories_table.php`

Data: **0** rows in `categories` at audit time.

## `category_merchant` pivot

Present: `category_id`, `merchant_id`, unique pair, FK cascade on delete.

Migration: `database/migrations/2025_03_11_010955_create_category_merchant_table.php`

Data: **0** merchant assignments at audit time.

## Trader category storage

- `user_metas.allowed_categories` — JSON, nullable. Migration `2025_03_11_140339_add_allowed_categories_to_user_metas_table.php`.
- Used by `app/Http/Controllers/Trader/SettingController.php` and `resources/js/Pages/Settings/Trader/Index.vue`.
- `category_user` pivot — **does not exist**.

## Application code (audit)

| Area | Finding |
|------|---------|
| `app/Models/Category.php` | `name`, `slug`, `description` fillable; no `enabled_by_default` |
| `CategoryController` | `description` validated as `nullable\|string` |
| Admin category routes | Commented out in `routes/web.php` |
| `merchant_traffic_categories_enabled` | Not in `SettingsService` |
| `FindAvailablePaymentDetail` | No category / `allowed_categories` filtering |
| `Admin\MerchantController` | Syncs `categories` on merchant settings |

## Step 1 conclusions

- **`description`**: exists; nullable in DB and optional in validation. Product spec requires required text for trader tooltips — align in step 2.
- **`enabled_by_default`**: missing everywhere; add in step 2 migration.

## Next implementation step

Step 2: migrations for `enabled_by_default`, optional `category_user` pivot, and tightening `description` to required.
