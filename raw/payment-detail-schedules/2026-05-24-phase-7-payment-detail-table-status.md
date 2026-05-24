# Payment Detail Work Schedule — Phase 7 (Payment Detail Table Status)

> Source: Cursor implementation session (p2p.cti)
> Collected: 2026-05-24
> Published: Unknown

## Summary

Phase 7 surfaces per-payment-detail schedule state in the payment detail index table (desktop) and mobile cards. No backend changes: `PaymentDetailResource` already exposes `schedule` via `PaymentDetailScheduleAvailabilityService::resolveStatusForPaymentDetail()` and the index controller already eager-loads `schedule.intervals`.

## Frontend artifacts

### Status resolver (client)

- `resources/js/utils/paymentDetailScheduleStatus.js`
  - `resolvePaymentDetailScheduleDisplay(schedule, offsetMs)` — recomputes status from `today_intervals` using server timezone (`Intl`) and offset derived from `schedule.server_now`
  - Status keys align with `App\Enums\PaymentDetailScheduleStatus`: `not_configured`, `working`, `day_off`, `starts_later`, `break_until`, `finished`, `invalid`
  - `break_until` label: `Перерыв до HH:mm` (server wall clock, not browser-local edit fields)
  - `scheduleStatusBadgeClass(status)` — DaisyUI badge classes per status

### Page-level tick (single timer)

- `resources/js/composables/usePaymentDetailScheduleTableTick.js`
  - `provide` tick ref (30s interval) and `serverTimeOffsetMs` synced from first row with `schedule.server_now`
  - On server calendar date change (in app timezone): `router.reload({ only: ['paymentDetails'], preserveScroll: true })` so `today_intervals` stay correct after midnight
  - Symbols: `PAYMENT_DETAIL_SCHEDULE_TICK_KEY`, `PAYMENT_DETAIL_SCHEDULE_OFFSET_KEY`

### Table cell component

- `resources/js/Components/PaymentDetail/PaymentDetailScheduleStatus.vue`
  - Injects tick + offset; renders badge (`statusLabel`), schedule name, interval line (`09:00-19:00` or `—`)
  - `not_configured`: label «Без расписания», interval `—`
  - Prop `compact` for table/mobile density

### Index integration

- `resources/js/Pages/PaymentDetail/Index.vue`
  - Calls `usePaymentDetailScheduleTableTick(paymentDetails)` at setup
  - Desktop (`xl+`): column **«Расписание»** between «Лимиты» and «Статус»
  - Mobile cards: block «Расписание» under requisites, above limits
  - Visible for trader and admin index (same `paymentDetails` payload)

## UX rules implemented

- Display is **informational**; traffic eligibility remains backend `availableBySchedule()` (Phase 5)
- Editable schedule times elsewhere remain server-time labels; table does not convert HH:mm to browser local
- One page timer, no per-row `setInterval`
- Client recomputes labels between reloads; boundary at midnight triggers partial Inertia reload

## Not in Phase 7

- Dedicated admin/Team Leader schedule read surfaces beyond existing index column (Phase 8)
- Manual verification checklist (Phase 9)
- Optional relative countdown text («Начнёт через …») — static labels only in v1 column

## Acceptance mapping

| Criterion | Met |
|-----------|-----|
| Status labels understandable (RU) | Yes — badge + name + interval |
| Break shows `Перерыв до HH:mm` | Yes — client resolver |
| Distinguishes no schedule / day off / starts later / working / break / finished | Yes |
| Server-time interval display | Yes — uses `starts_at`/`ends_at` from payload |
