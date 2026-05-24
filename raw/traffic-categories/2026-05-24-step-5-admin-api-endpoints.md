# Step 5 — Admin API Endpoints

> Source: repository implementation session
> Collected: 2026-05-24
> Published: 2026-05-24

## Summary

Exposed JSON admin API for the category management modal and merchant category assignment modal. Step 5 of the implementation plan is complete; trader API (step 6), `FindAvailablePaymentDetail` filtering (step 7), and target UI (steps 8–9) remain pending.

## Controller

`App\Http\Controllers\Admin\MerchantTrafficCategoryController`

Response envelope matches trader schedule API pattern: `{ "success": true, "data": ... }`.

## Routes (`routes/web.php`, middleware `auth`, `banned`, `role:Super Admin`, prefix `admin`)

| Method | Path | Route name | Action |
|--------|------|------------|--------|
| GET | `/admin/traffic-categories` | `admin.traffic-categories.index` | List categories + global `merchant_traffic_categories_enabled` |
| POST | `/admin/traffic-categories` | `admin.traffic-categories.store` | Create category |
| PATCH | `/admin/traffic-categories/settings/enabled` | `admin.traffic-categories.settings.enabled.update` | Toggle global feature |
| PATCH | `/admin/traffic-categories/{category}` | `admin.traffic-categories.update` | Update category |
| DELETE | `/admin/traffic-categories/{category}` | `admin.traffic-categories.destroy` | Delete category |
| POST | `/admin/traffic-categories/{category}/apply-to-all-traders` | `admin.traffic-categories.apply-to-all-traders` | Mass apply default to all traders |
| PATCH | `/admin/merchants/{merchant}/categories` | `admin.merchants.categories.update` | Sync merchant `category_ids` |

`Route::resource('/categories', CategoryController::class)` remains commented out. Inertia `Category/*` pages are legacy; modal UX should use the JSON API above.

## Form requests

- `App\Http\Requests\Admin\TrafficCategory\StoreRequest` — `name`, `description`, optional `enabled_by_default`
- `App\Http\Requests\Admin\TrafficCategory\UpdateRequest` — same as store
- `App\Http\Requests\Admin\TrafficCategory\UpdateEnabledRequest` — `enabled` boolean
- `App\Http\Requests\Admin\TrafficCategory\SyncMerchantCategoriesRequest` — `category_ids` present array; `category_ids.*` exists in `categories`

## Request / response notes

### GET index `data`

- `merchant_traffic_categories_enabled` (bool)
- `categories` — `CategoryResource` collection (resolved)

### POST/PATCH category body

```json
{
  "name": "string",
  "description": "string",
  "enabled_by_default": false
}
```

### PATCH settings/enabled body

```json
{ "enabled": true }
```

### PATCH merchants/{merchant}/categories body

```json
{ "category_ids": [1, 2] }
```

Empty `category_ids` clears all assignments (merchant unrestricted by categories).

### POST apply-to-all-traders

No body. Uses category's current `enabled_by_default` via `MerchantTrafficCategoryService::applyToAllTraders()`.

## DTO

`TrafficCategoryUpsertDTO::makeFromRequest(array $data, bool $enabledByDefault)` added for store/update.

## Ziggy

After route changes: `php artisan optimize` and `php artisan ziggy:generate resources/js/ziggy-routes.js`.

## Still pending

- Trader endpoints (step 6)
- `FindAvailablePaymentDetail` filter (step 7)
- Admin/trader UI wired to these routes (steps 8–9)
