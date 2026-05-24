# Phase 8: Role Read Views

**Source:** repository implementation, 2026-05-24  
**Collected:** 2026-05-24  
**Published:** 2026-05-24

## Summary

Read-only schedule visibility for Super Admin and Team Leader on existing payment detail pages. No new schedule mutation routes or manager UI for non-traders.

## Backend

- `PaymentDetailQueriesEloquent::paginateForAdmin()` and `paginateForTeamLeader()` eager-load `schedule.intervals` so `PaymentDetailResource` schedule payload resolves correctly.
- `BulkUpdateRequest::authorize()` rejects `schedule_apply` / `schedule_remove` when not on Trader routes (defense in depth; schedule CRUD already gated by `PaymentDetailScheduleController::ensureTrader()`).

## Frontend

- `Leader/Trader/PaymentDetails.vue`: «Расписание» column (desktop + mobile) via `PaymentDetailScheduleStatus` and `usePaymentDetailScheduleTableTick`.
- `PaymentDetail/Index.vue`: schedule manager/quick-create modals mounted only for trader view (`isTraderView`).
- `PaymentDetailEditModal.vue`: read-only schedule block for admin/team leader when detail has a schedule (`PaymentDetailScheduleStatus` + helper text).

## Out of scope (v1)

- Analyst user payment details page
- Dedicated admin/team leader schedule manager pages
- Schedule list API for non-traders
