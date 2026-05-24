# Payment Detail Work Schedule — Phase 5 (Traffic Selection and Availability Queries)

> Source: Cursor implementation session (p2p.cti)
> Collected: 2026-05-24
> Published: Unknown

## Summary

Phase 5 applies the shared `PaymentDetail::availableBySchedule()` scope (via `PaymentDetailScheduleAvailabilityService::applyAvailableBySchedule`) to traffic selection and availability counters so scheduled payment details are excluded outside working intervals.

## Backend changes

### Order selection

- `app/Services/Order/Features/OrderDetailProvider/Classes/FindAvailablePaymentDetail.php` — `->availableBySchedule()` after `->active()` in `queryPaymentDetails()`

### Admin enabled cards

- `app/Http/Controllers/Admin/EnabledCardsController.php` — private `trafficAvailablePaymentDetailsQuery()` centralizes active/online/schedule filter for count, currency limits, potential limits, min-amount group stats, and active detail id lists

### Merchant min-amount stats

- `app/Services/EnabledCards/MinAmountStatsService.php` — `activePaymentDetailsQuery()` includes `->availableBySchedule()`

### Sidebar counters

- `app/Http/Middleware/HandleInertiaRequests.php` — trader and admin `active_details_*` cache queries include `->availableBySchedule()`

## Intentionally unchanged

- Payment detail index/list queries (`PaymentDetailQueriesEloquent`) — manual `is_active` filter only; not traffic selection
- `PaymentDetailEnabledPeriodService` — historical enabled periods, not schedule availability
- Trader analytics historical charts — use enabled periods, not live traffic eligibility

## Not in Phase 5

- Schedule manager UI (Phase 6)
- Payment detail table schedule column (Phase 7)
- Dedicated admin/team leader schedule read views (Phase 8)
