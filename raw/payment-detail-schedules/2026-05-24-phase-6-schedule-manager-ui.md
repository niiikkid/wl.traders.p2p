# Payment Detail Work Schedule — Phase 6 (Schedule Manager UI)

> Source: Cursor implementation session (p2p.cti)
> Collected: 2026-05-24
> Published: Unknown

## Summary

Phase 6 delivers full trader schedule management in the browser: list, create, edit (with default weekdays/times and per-day override intervals), and copy. The manager reuses existing JSON API routes from Phase 3 (`payment-detail-schedules.*`). No backend changes. No delete action in v1.

## Frontend artifacts

### Composable — editor state

- `resources/js/composables/usePaymentDetailScheduleEditor.js`
  - `WEEKDAY_OPTIONS` (ISO 1–7, labels Пн–Вс)
  - `intervalsToEditorState()` — API intervals → form (default days/times + `dayOverrides`)
  - `editorStateToIntervals()` — form → API payload
  - `validateEditorStateLocally()` — HH:mm, start < end, no overlaps per day, at least one effective interval
  - helpers: `toggleDefaultDay`, `setDayOverrideEnabled`, `addDayOverrideInterval`, `removeDayOverrideInterval`

### Components and modals

- `resources/js/Components/PaymentDetail/PaymentDetailScheduleForm.vue` — name, server-time helper, default days (toggle buttons), default start/end, per-day override blocks with multiple intervals; shared-edit warning when `payment_details_count > 0`
- `resources/js/Modals/PaymentDetailSchedule/PaymentDetailScheduleManagerModal.vue`
  - left: schedule list (name, `status_label`, `payment_details_count`)
  - right: create/edit form or copy name-only panel
  - save create → `POST payment-detail-schedules.store`
  - save edit → `PATCH payment-detail-schedules.update` (with `ConfirmModal` when attached details > 0)
  - copy → `POST payment-detail-schedules.copy` (name only; intervals copied server-side)
  - no delete control

### Integration

- `resources/js/store/modal.js` — `paymentDetailScheduleManager` + `openPaymentDetailScheduleManagerModal({ scheduleId?, onCreated? })`
- `resources/js/Components/PaymentDetail/PaymentDetailScheduleField.vue` — button «Управлять расписаниями»; refreshes schedule list after manager/quick-create close
- `resources/js/Pages/PaymentDetail/Index.vue` — trader menu action «Расписания работы»; registers `PaymentDetailScheduleManagerModal`

### Unchanged from Phase 4

- `PaymentDetailScheduleQuickCreateModal.vue` — minimal Mon–Fri 09:00–19:00 create remains for fast path

## UX rules implemented

- All editable times are server time (helper shows `server_now` / `server_timezone` from index API)
- Per-day override replaces default interval for that day (not additive)
- Client validates before submit; Laravel validation errors mapped to fields
- Submit buttons disabled while `processing`

## Entry points

| Location | Action |
|----------|--------|
| Payment detail index (trader) | Table actions → «Расписания работы» |
| Create/edit payment detail modal | `PaymentDetailScheduleField` → «Управлять расписаниями» |
| Same field | «Создать расписание» still opens quick-create modal |

## Not in Phase 6

- Payment detail index table schedule status column (Phase 7)
- Admin / Team Leader read-only schedule views (Phase 8)
- Automated / manual verification pass (Phase 9)
