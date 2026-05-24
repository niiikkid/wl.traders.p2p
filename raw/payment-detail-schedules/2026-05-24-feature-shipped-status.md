# Payment Detail Work Schedule — Feature Shipped

> Source: user confirmation, project wiki update request
> Collected: 2026-05-24
> Published: 2026-05-24

## Summary

The payment detail work schedule feature (расписание по реквизитам) is fully implemented and verified. All implementation phases 0–9 are complete.

## Scope Delivered

- Trader-owned named schedules with working days, default intervals, and per-day overrides
- Schedule CRUD API, manager UI, quick-create, copy
- Payment detail create/edit/bulk attach and remove schedule
- Server-time traffic filtering via `availableBySchedule()` in order selection and availability counters
- Index table and mobile schedule status column with client-side tick
- Admin and Team Leader read-only schedule visibility on payment detail pages
- Manual verification checklist (Phase 9) completed

## Feature Status

**Shipped.** No pending implementation phases remain for v1.
