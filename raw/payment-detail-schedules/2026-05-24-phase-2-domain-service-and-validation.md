# Payment Detail Work Schedule — Phase 2 (Domain Service and Validation)

> Source: Cursor implementation session (p2p.cti)
> Collected: 2026-05-24
> Published: Unknown

## Summary

Phase 2 of the payment detail work schedule feature: centralized interval normalization/validation, schedule CRUD service (create/update/copy), status resolver, availability query helper, Form Requests, and `PaymentDetail::scopeAvailableBySchedule()`. No API routes, controllers, resources, or UI yet.

## Enum

### `PaymentDetailScheduleStatus`

Values: `not_configured`, `working`, `day_off`, `starts_later`, `break_until`, `finished`, `invalid`. Method `label(?string $time)` returns Russian UI labels.

## DTOs

- `PaymentDetailScheduleIntervalData` — normalized interval row (`day_of_week`, `starts_at`, `ends_at` as `HH:mm:ss`)
- `PaymentDetailScheduleUpsertDTO` — `name` + `intervals[]`
- `PaymentDetailScheduleCopyDTO` — `name`

## Services

### `PaymentDetailScheduleIntervalNormalizer`

- Parses `HH:mm` or `HH:mm:ss`
- Sorts by day and start time
- Rejects empty list, invalid day (not 1–7), invalid times, overnight (`starts_at >= ends_at`), overlapping same-day intervals
- Throws `ValidationException` with field-level messages

### `PaymentDetailScheduleService`

- `create(int $user_id, PaymentDetailScheduleUpsertDTO)` — transaction, create schedule + intervals
- `update(PaymentDetailSchedule, PaymentDetailScheduleUpsertDTO)` — lock schedule, update name, replace intervals atomically
- `copy(PaymentDetailSchedule, PaymentDetailScheduleCopyDTO)` — independent copy with same intervals

### `PaymentDetailScheduleAvailabilityService`

- `applyAvailableBySchedule(Builder $query, ?CarbonInterface $at)` — SQL filter: null schedule OR current server weekday/time inside interval (start inclusive, end exclusive)
- `isAvailableBySchedule(PaymentDetail, ?CarbonInterface $at)`
- `resolveStatus(PaymentDetailSchedule, ?CarbonInterface $at)` — status payload with `today_intervals`, `current_interval`, `next_interval`, ISO moments
- `resolveStatusForPaymentDetail(PaymentDetail, ?CarbonInterface $at)` — null when no schedule attached

## Validation

- Rule `PaymentDetailScheduleIntervals` wraps normalizer for Form Requests
- Form Requests: `PaymentDetailSchedule/StoreRequest`, `UpdateRequest`, `CopyRequest`

## Model

- `PaymentDetail::scopeAvailableBySchedule(?CarbonInterface $at)` delegates to availability service

## Not in Phase 2

- Schedule CRUD routes/controller/resources (Phase 3)
- Payment detail assignment fields (Phase 4)
- Traffic selection integration (Phase 5)
- Vue UI (Phases 6–7)

## Acceptance (Phase 2 plan)

- Invalid intervals rejected — yes (normalizer + rule)
- Empty schedules rejected — yes
- Overlapping same-day intervals rejected — yes
- Overnight intervals rejected — yes (`starts_at >= ends_at`)
- Editing replaces intervals atomically — yes (service)
- Status resolver covers working, day off, starts later, break, finished — yes
- Shared availability query helper — yes (`applyAvailableBySchedule` + scope)
