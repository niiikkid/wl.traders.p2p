# Payment Detail Work Schedule — Phase 1 (Database and Models)

> Source: Cursor implementation session (p2p.cti)
> Collected: 2026-05-24
> Published: Unknown

## Summary

Phase 1 of the payment detail work schedule feature: database schema and Eloquent models. No API routes, services, UI, or traffic filtering yet.

## Migrations

### `2026_05_24_130610_create_payment_detail_schedules_table.php`

Creates:

- `payment_detail_schedules`: `id`, `user_id` (FK cascade), `name`, timestamps; unique `(user_id, name)`; index on `user_id`.
- `payment_detail_schedule_intervals`: `id`, `payment_detail_schedule_id`, `day_of_week` (unsigned tiny int, ISO 1–7), `starts_at` / `ends_at` (`time`), timestamps; FK `pdsi_schedule_fk` cascade on delete; index `pdsi_schedule_day_idx` on `(payment_detail_schedule_id, day_of_week)`.

Note: MySQL 64-character identifier limit required short FK names (`pdsi_schedule_fk`, `pd_schedule_fk`) instead of Laravel auto-generated names.

`source_type` column omitted — plan allows storing only effective expanded intervals.

### `2026_05_24_130620_add_payment_detail_schedule_id_to_payment_details_table.php`

Adds nullable `payment_detail_schedule_id` on `payment_details` after `user_id`; FK `pd_schedule_fk` with `nullOnDelete`; index `pd_schedule_id_idx`.

## Models

### `PaymentDetailSchedule`

- `$fillable`: `user_id`, `name`
- Relations: `user()`, `intervals()`, `paymentDetails()`
- `declare(strict_types=1)`

### `PaymentDetailScheduleInterval`

- `$fillable`: `payment_detail_schedule_id`, `day_of_week`, `starts_at`, `ends_at`
- Cast: `day_of_week` → integer
- Relation: `schedule()` → `PaymentDetailSchedule`
- `declare(strict_types=1)`

### `PaymentDetail` (updated)

- `$fillable` includes `payment_detail_schedule_id`
- PHPDoc: nullable `payment_detail_schedule_id`, `PaymentDetailSchedule|null $schedule`
- Relation: `schedule()` → `belongsTo(PaymentDetailSchedule::class, 'payment_detail_schedule_id')`

## Not in Phase 1

- Form requests, services, status resolver, `scopeAvailableBySchedule`
- Schedule CRUD API and Ziggy routes
- Payment detail resource/UI/bulk edit schedule fields
- `FindAvailablePaymentDetail` and availability count integration
- Factories for schedule models

## Acceptance (Phase 1 plan)

- Schedules represented independently of payment details — yes
- Payment details can reference no schedule or one schedule — yes (nullable FK)
- Schedule names unique per user — yes (DB unique)
- Intervals linked to schedules with cascade on delete — yes
