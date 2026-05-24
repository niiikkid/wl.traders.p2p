# Step 6 — Trader API Endpoints

> Source: repository implementation session
> Collected: 2026-05-24
> Published: 2026-05-24

## Summary

Exposed JSON trader API for reading traffic categories with per-trader `enabled` state and toggling a single category. Step 6 of the implementation plan is complete; `FindAvailablePaymentDetail` filtering (step 7) and target UI (steps 8–9) remain pending.

## Controller

`App\Http\Controllers\Trader\TrafficCategoryController`

- `ensureTrader()` via `isRouteFor('Trader')` (same pattern as `PaymentDetailScheduleController`, `PaymentDetailTagController`).
- `ensureFeatureEnabled()` on PATCH only — aborts 403 when global `merchant_traffic_categories_enabled` is off.

## Resource

`App\Http\Resources\TraderTrafficCategoryResource` — trader-facing shape: `id`, `name`, `slug`, `description`, `enabled`. Does not expose `enabled_by_default` (admin-only).

## Routes (`routes/web.php`, middleware `auth`, `banned`, `role:Trader|Super Admin`, same group as payment-detail-schedules)

| Method | Path | Route name | Action |
|--------|------|------------|--------|
| GET | `/traffic-categories` | `traffic-categories.index` | List categories + global flag |
| PATCH | `/traffic-categories/{category}/enabled` | `traffic-categories.enabled.update` | Toggle trader category |

## Form request

`App\Http\Requests\Trader\TrafficCategory\UpdateCategoryEnabledRequest` — `enabled` required boolean.

## Request / response notes

### GET index `data`

When global feature is **disabled**:

- `merchant_traffic_categories_enabled`: `false`
- `categories`: `[]` (no category rows returned)

When global feature is **enabled**:

- `merchant_traffic_categories_enabled`: `true`
- `categories`: `TraderTrafficCategoryResource` collection (resolved)
- `enabled` per category from `category_user` pivot; missing pivot row → `false`

### PATCH `{category}/enabled` body

```json
{ "enabled": true }
```

Calls `MerchantTrafficCategoryService::setTraderCategoryEnabled()` (updates pivot + legacy `user_metas.allowed_categories` JSON).

Response `data.category` — single `TraderTrafficCategoryResource`.

Returns **403** when global feature is disabled (`Категории трафика отключены.`).

## Service delegation

No new service methods; reuses `setTraderCategoryEnabled()` from step 4.

## Ziggy

After route changes: `php artisan optimize` and `php artisan ziggy:generate resources/js/ziggy-routes.js`.

## UX decision

Trader toggles save **immediately per category** via PATCH (not a batch save button). Step 9 UI should call `traffic-categories.enabled.update` on click.

## Still pending

- `FindAvailablePaymentDetail` filter (step 7)
- Admin/trader UI wired to these routes (steps 8–9)
