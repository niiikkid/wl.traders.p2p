# Payment Detail Work Schedule — Phase 4 (Payment Detail Assignment)

> Source: Cursor implementation session (p2p.cti)
> Collected: 2026-05-24
> Published: Unknown

## Summary

Phase 4 wires nullable `payment_detail_schedule_id` through payment detail create, update, and bulk edit. Traders can attach, change, or remove a schedule on a payment detail without changing `is_active`. Schedule ownership is validated on every write. Admin/team leader updates to payment details do not mutate schedule assignment (only the detail owner trader can).

Traffic selection (`FindAvailablePaymentDetail`) is still unchanged — Phase 5.

## Backend

### Validation rule

- `app/Rules/OwnedPaymentDetailSchedule.php` — ensures schedule `user_id` matches the target trader (create: `auth()->id()`; update: payment detail `user_id`; bulk: `auth()->id()`)

### Form requests

- `app/Http/Requests/PaymentDetail/StoreRequest.php` — nullable `payment_detail_schedule_id`
- `app/Http/Requests/PaymentDetail/UpdateRequest.php` — `payment_detail_schedule_id` only when `auth()->id() === paymentDetail.user_id`
- `app/Http/Requests/PaymentDetail/BulkUpdateRequest.php` — bulk fields `schedule_apply` and `schedule_remove`; `payment_detail_schedule_id` required when `schedule_apply` is in `fields`

### DTOs and service

- `app/DTO/PaymentDetail/PaymentDetailCreateDTO.php` — `payment_detail_schedule_id`
- `app/DTO/PaymentDetail/PaymentDetailUpdateDTO.php` — `payment_detail_schedule_id` + `updates_schedule` flag
- `app/Services/PaymentDetail/PaymentDetailService.php` — persists schedule on create; updates `payment_detail_schedule_id` only when `updates_schedule` is true

### Controller

- `app/Http/Controllers/PaymentDetailController.php`
  - `update()` — `PaymentDetailUpdateDTO::makeFromRequest($validated, $updatesSchedule)` where `$updatesSchedule = auth user is detail owner`
  - `bulkUpdate()` — `buildBulkUpdatePayload()` handles `schedule_apply` / `schedule_remove`
  - `index()` / `show()` — eager load `schedule.intervals`

### Resource and queries

- `app/Http/Resources/PaymentDetailResource.php` — `payment_detail_schedule_id`, `schedule` via `resolveStatusForPaymentDetail()`
- `app/Queries/Eloquent/PaymentDetailQueriesEloquent.php` — `paginateForUser()` loads `schedule.intervals`

## Frontend

### Composable and components

- `resources/js/composables/usePaymentDetailSchedules.js` — fetch `payment-detail-schedules.index`, cache list, `buildDefaultWeekdayIntervals()` (Mon–Fri 09:00–19:00)
- `resources/js/Components/PaymentDetail/PaymentDetailScheduleField.vue` — select, clear, preview, server-time hint, open quick create
- `resources/js/Modals/PaymentDetailSchedule/PaymentDetailScheduleQuickCreateModal.vue` — minimal create (name + default weekdays) via `payment-detail-schedules.store`; auto-select via `onCreated` callback

### Modal integration

- `resources/js/Modals/PaymentDetail/PaymentDetailCreateModal.vue` — `PaymentDetailScheduleField` when trader view
- `resources/js/Modals/PaymentDetail/PaymentDetailEditModal.vue` — field when `canEditSchedule` (trader view + owner)
- `resources/js/Modals/PaymentDetail/PaymentDetailBulkEditModal.vue` — checkboxes `schedule_apply` / `schedule_remove`; mutual exclusion validated client-side
- `resources/js/Pages/PaymentDetail/Index.vue` — registers `PaymentDetailScheduleQuickCreateModal`
- `resources/js/store/modal.js` — `paymentDetailScheduleQuickCreate` modal + `openPaymentDetailScheduleQuickCreateModal()`

## Bulk edit payload

When `fields` includes:

- `schedule_apply` — sets `payment_detail_schedule_id` from request (required)
- `schedule_remove` — sets `payment_detail_schedule_id` to `null`

Cannot apply both in one request (frontend + should validate on backend via separate field checks).

## Not in Phase 4

- `FindAvailablePaymentDetail` schedule filter (Phase 5)
- Enabled cards / sidebar schedule-aware counts (Phase 5)
- Full schedule manager modal with per-day overrides (Phase 6)
- Payment detail index table schedule column (Phase 7)
- Admin/team leader dedicated read-only schedule UI (Phase 8)
