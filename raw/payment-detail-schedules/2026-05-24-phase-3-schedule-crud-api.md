# Payment Detail Work Schedule — Phase 3 (Schedule CRUD API)

> Source: Cursor implementation session (p2p.cti)
> Collected: 2026-05-24
> Published: Unknown

## Summary

Phase 3 exposes trader-owned schedule CRUD over JSON web routes (same middleware group as payment details and payment-detail-tags). Thin `PaymentDetailScheduleController` delegates to `PaymentDetailScheduleService` and Form Requests from Phase 2. Admin/Team Leader mutation is not exposed; read-only schedule data for those roles remains planned via payment detail resources (Phase 8).

## Routes (`routes/web.php`, Trader|Super Admin middleware)

| Method | URI | Route name |
|--------|-----|------------|
| GET | `/payment-detail-schedules` | `payment-detail-schedules.index` |
| POST | `/payment-detail-schedules` | `payment-detail-schedules.store` |
| PATCH | `/payment-detail-schedules/{paymentDetailSchedule}` | `payment-detail-schedules.update` |
| POST | `/payment-detail-schedules/{paymentDetailSchedule}/copy` | `payment-detail-schedules.copy` |

After route changes: `php artisan optimize` and `php artisan ziggy:generate resources/js/ziggy-routes.js`.

## Controller

`app/Http/Controllers/PaymentDetailScheduleController.php`

- `index()` — current user's schedules with `intervals` (ordered by day, start), `paymentDetails` count, `server_timezone`, `server_now`
- `store(StoreRequest)` — `PaymentDetailScheduleUpsertDTO::makeFromRequest($request->validated())`
- `update(UpdateRequest, PaymentDetailSchedule)` — owner check + upsert
- `copy(CopyRequest, PaymentDetailSchedule)` — owner check + `PaymentDetailScheduleCopyDTO`
- `ensureTrader()` — `isRouteFor('Trader')` or 403 (same pattern as `PaymentDetailTagController`)
- `ensureOwner(PaymentDetailSchedule)` — `user_id === auth()->id()` or 403

## API Resources

- `app/Http/Resources/PaymentDetailScheduleResource.php` — merges `PaymentDetailScheduleAvailabilityService::resolveStatus()` with `payment_details_count` (when counted), `intervals`, `created_at`, `updated_at` (ISO)
- `app/Http/Resources/PaymentDetailScheduleIntervalResource.php` — `id`, `day_of_week`, `starts_at`, `ends_at` as `HH:mm`

## Response shapes

**Index:**

```json
{
  "success": true,
  "data": {
    "server_timezone": "Asia/Bangkok",
    "server_now": "2026-05-24T12:00:00+07:00",
    "schedules": [ /* PaymentDetailScheduleResource[] */ ]
  }
}
```

**Store / update / copy:**

```json
{
  "success": true,
  "data": { /* single PaymentDetailScheduleResource */ }
}
```

Payloads unchanged from Phase 2 Form Requests (`name` + `intervals[]` for store/update; `name` only for copy).

## Authorization

- Mutations: trader owner only
- No delete endpoint (v1 product)
- Admin/Team Leader: no schedule mutation routes in v1

## Not in Phase 3

- `payment_detail_schedule_id` on payment detail create/edit/bulk (Phase 4)
- `PaymentDetailResource` schedule fields (Phase 4/7)
- Traffic selection / enabled cards / sidebar counts (Phase 5)
- Vue schedule manager and payment detail modals (Phases 6–7)
