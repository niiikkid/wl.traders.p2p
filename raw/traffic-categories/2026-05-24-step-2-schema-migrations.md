# Step 2 Schema Migrations — Merchant Traffic Categories

> Source: Repository implementation (`database/migrations/2026_05_24_173658_add_merchant_traffic_category_schema_to_categories_table.php`, models, controllers)
> Collected: 2026-05-24
> Published: Unknown

## Purpose

Implementation plan step 2: add `enabled_by_default`, require `description`, create `category_user` pivot, backfill from `user_metas.allowed_categories`.

## Migration applied

`database/migrations/2026_05_24_173658_add_merchant_traffic_category_schema_to_categories_table.php`

### `categories` changes

- Added `enabled_by_default` — `boolean`, default `false`, after `description`
- `description` — existing `NULL` values set to `''`, then column changed to **NOT NULL** `text`

### `category_user` table (new)

- `id`
- `category_id` — FK `categories`, cascade on delete
- `user_id` — FK `users`, cascade on delete
- `enabled` — `boolean`, default `true`
- `created_at`, `updated_at`
- Unique `(category_id, user_id)`
- Index `(user_id, enabled)`

### Backfill

From `user_metas.allowed_categories` JSON arrays: insert `category_user` rows with `enabled = true` for each valid category id (`insertOrIgnore`, chunked by 500). Empty/null JSON → no rows.

## Models

- `Category`: `enabled_by_default` fillable + cast; `traders()` belongsToMany via `category_user` with pivot `enabled`
- `User`: `trafficCategories()` belongsToMany via `category_user` with pivot `enabled`

## Admin CRUD (legacy pages, routes still commented)

- `CategoryController`: `description` required; `enabled_by_default` boolean
- `CategoryResource`: includes `enabled_by_default`
- `resources/js/Pages/Category/Create.vue`, `Edit.vue`, `Index.vue`: checkbox + table column for default flag

## Not changed in step 2

- `merchant_traffic_categories_enabled` setting (step 3)
- `FindAvailablePaymentDetail` filtering (step 7)
- `user_metas.allowed_categories` still used by trader settings UI
- Admin category routes still commented in `routes/web.php`
