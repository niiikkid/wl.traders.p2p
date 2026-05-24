# Payment Detail Work Schedule Implementation Plan

> Sources: User conversation, 2026-05-22; repository exploration, 2026-05-22; Phase 1 implementation, 2026-05-24; Phase 2 implementation, 2026-05-24; Phase 3 implementation, 2026-05-24; Phase 4 implementation, 2026-05-24; Phase 5 implementation, 2026-05-24; Phase 6 implementation, 2026-05-24; Phase 7 implementation, 2026-05-24; Phase 8 implementation, 2026-05-24
> Raw: [Payment Detail Work Schedule Requirements](../../raw/payment-detail-schedules/2026-05-22-payment-detail-work-schedule-requirements.md); [Phase 1 Database and Models](../../raw/payment-detail-schedules/2026-05-24-phase-1-database-and-models.md); [Phase 2 Domain Service and Validation](../../raw/payment-detail-schedules/2026-05-24-phase-2-domain-service-and-validation.md); [Phase 3 Schedule CRUD API](../../raw/payment-detail-schedules/2026-05-24-phase-3-schedule-crud-api.md); [Phase 4 Payment Detail Assignment](../../raw/payment-detail-schedules/2026-05-24-phase-4-payment-detail-assignment.md); [Phase 5 Traffic Selection](../../raw/payment-detail-schedules/2026-05-24-phase-5-traffic-selection.md); [Phase 6 Schedule Manager UI](../../raw/payment-detail-schedules/2026-05-24-phase-6-schedule-manager-ui.md); [Phase 7 Payment Detail Table Status](../../raw/payment-detail-schedules/2026-05-24-phase-7-payment-detail-table-status.md); [Phase 8 Role Read Views](../../raw/payment-detail-schedules/2026-05-24-phase-8-role-read-views.md)
> Updated: 2026-05-24

## Overview

Payment detail work schedules add a server-time availability layer on top of the existing trader online state and payment detail active state. A payment detail still remains manually enabled or disabled by the trader, but when a schedule is attached to it, traffic can use that detail only inside the schedule's configured working intervals. Schedules are named trader-owned entities that may be attached to many payment details; editing a schedule affects every attached detail.

As of **2026-05-24**, phases **0–8 are implemented in code**: schema through role read views for admin and Team Leader. Phase **9** (verification) remains pending.

## Implementation Progress

| Phase | Status | Summary |
|-------|--------|---------|
| 0 — Discovery | Done | Spec and integration anchors |
| 1 — Database/models | Done | `payment_detail_schedules`, intervals, FK on `payment_details` |
| 2 — Domain/validation | Done | Normalizer, CRUD service, `availableBySchedule`, status resolver |
| 3 — Schedule CRUD API | Done | Trader JSON routes and resources |
| 4 — Detail assignment | Done | Create/edit/bulk attach; quick-create modal |
| 5 — Traffic selection | Done | `FindAvailablePaymentDetail`, enabled cards, sidebar counts |
| 6 — Schedule manager UI | Done | Manager modal, form, per-day overrides, copy |
| 7 — Table status column | Done | Index column + mobile block; client status tick |
| 8 — Role read views | Done | Admin index + TL trader requisites; read-only in edit modal |
| 9 — Verification | Pending | Manual checklist |

## Product Scope

The first version supports trader-owned work schedules for pay-in payment details:

- a trader can create named schedules;
- a schedule has working days, a default day interval, and optional per-day interval overrides;
- a payment detail may have no schedule or one attached schedule;
- no schedule attached means the payment detail behaves exactly as it does today;
- attaching a schedule restricts traffic to the schedule's current working interval;
- removing the schedule from a payment detail disables schedule filtering for that detail;
- schedules cannot be deleted in the first version;
- schedules can be edited, and changes apply to all payment details that use them;
- schedules can be copied to create an independent schedule with a new name;
- bulk edit can attach a schedule to many payment details or remove schedules from many payment details;
- Team Leaders and admins can view trader schedules attached to payment details;
- Team Leaders and admins do not edit trader schedules in the first version.

The feature must not auto-toggle `payment_details.is_active`. Manual active state remains independent:

- `is_active = false` always means the payment detail cannot receive traffic;
- `user.is_online = false` or `user.stop_traffic = true` always means the user's payment details cannot receive traffic;
- a schedule only narrows availability after the existing manual and online checks pass.

## Existing Code Anchors

The implementation should integrate with these existing areas:

- `app/Models/PaymentDetail.php` stores the manual payment detail state; **`schedule()` relation (Phase 1)**; **`scopeAvailableBySchedule()` (Phase 2)**.
- `app/Services/PaymentDetail/PaymentDetailScheduleService.php` — create/update/copy schedules with atomic interval replace **(Phase 2)**.
- `app/Services/PaymentDetail/PaymentDetailScheduleIntervalNormalizer.php` — parse, validate, and normalize interval payloads **(Phase 2)**.
- `app/Services/PaymentDetail/PaymentDetailScheduleAvailabilityService.php` — shared SQL availability filter and status payload resolver **(Phase 2)**.
- `app/Enums/PaymentDetailScheduleStatus.php` — status keys and Russian labels **(Phase 2)**.
- `app/Http/Requests/PaymentDetailSchedule/` — `StoreRequest`, `UpdateRequest`, `CopyRequest` **(Phase 2)**.
- `app/Http/Controllers/PaymentDetailScheduleController.php` — trader JSON CRUD **(Phase 3)**.
- `app/Http/Resources/PaymentDetailScheduleResource.php` and `PaymentDetailScheduleIntervalResource.php` **(Phase 3)**.
- Routes `payment-detail-schedules.*` in `routes/web.php` (Trader middleware group) **(Phase 3)**.
- `app/Services/Order/Features/OrderDetailProvider/Classes/FindAvailablePaymentDetail.php` — **`->availableBySchedule()` after `->active()` (Phase 5)**.
- `app/Http/Resources/PaymentDetailResource.php` — **`payment_detail_schedule_id` + `schedule` status payload (Phase 4)**; feeds index and create/edit modals.
- `app/Http/Requests/PaymentDetail/StoreRequest.php` and `app/Http/Requests/PaymentDetail/UpdateRequest.php` — **nullable `payment_detail_schedule_id` with `OwnedPaymentDetailSchedule` (Phase 4)**; update allows schedule only when auth user owns the detail.
- `app/Http/Requests/PaymentDetail/BulkUpdateRequest.php` — **bulk fields `schedule_apply` / `schedule_remove` (Phase 4)**.
- `app/Rules/OwnedPaymentDetailSchedule.php` — shared ownership validation **(Phase 4)**.
- `resources/js/Modals/PaymentDetail/PaymentDetailCreateModal.vue` and `PaymentDetailEditModal.vue` — **`PaymentDetailScheduleField` + quick create (Phase 4)**.
- `resources/js/Modals/PaymentDetail/PaymentDetailBulkEditModal.vue` — **apply/remove schedule actions (Phase 4)**.
- `resources/js/composables/usePaymentDetailSchedules.js`, `PaymentDetailScheduleField.vue`, `PaymentDetailScheduleQuickCreateModal.vue` **(Phase 4)**.
- `resources/js/composables/usePaymentDetailScheduleEditor.js` — intervals ↔ editor state, local validation **(Phase 6)**.
- `resources/js/Components/PaymentDetail/PaymentDetailScheduleForm.vue` — default days/times + per-day overrides **(Phase 6)**.
- `resources/js/Modals/PaymentDetailSchedule/PaymentDetailScheduleManagerModal.vue` — list/create/edit/copy **(Phase 6)**.
- `resources/js/store/modal.js` — `paymentDetailScheduleManager` + `openPaymentDetailScheduleManagerModal()` **(Phase 6)**.
- `resources/js/Pages/PaymentDetail/Index.vue` — quick-create + manager modals; trader action «Расписания работы»; **«Расписание» column + mobile block (Phase 7)**; `usePaymentDetailScheduleTableTick()` **(Phase 7)**.
- `resources/js/utils/paymentDetailScheduleStatus.js` — client status resolver for table display **(Phase 7)**.
- `resources/js/composables/usePaymentDetailScheduleTableTick.js` — single page timer + server offset **(Phase 7)**.
- `resources/js/Components/PaymentDetail/PaymentDetailScheduleStatus.vue` — table/card status cell **(Phase 7)**.
- `app/Http/Middleware/HandleInertiaRequests.php` — sidebar active detail counts with 15-second cache; **`->availableBySchedule()` (Phase 5)**.
- `app/Http/Controllers/Admin/EnabledCardsController.php` — **`trafficAvailablePaymentDetailsQuery()`** applies schedule filter to all enabled-card statistics **(Phase 5)**.
- `app/Services/EnabledCards/MinAmountStatsService.php` — **`activePaymentDetailsQuery()` includes `->availableBySchedule()` (Phase 5)**.
- `app/Models/PaymentDetailEnabledPeriod.php` and `app/Services/PaymentDetail/PaymentDetailEnabledPeriodService.php` track historical effective enabled periods and must not be reused as schedule storage.
- `app/Queries/Eloquent/PaymentDetailQueriesEloquent.php` — **`paginateForAdmin()` / `paginateForTeamLeader()` eager-load `schedule.intervals` (Phase 8)**.
- `app/Http/Requests/PaymentDetail/BulkUpdateRequest.php` — **`authorize()` blocks schedule bulk fields outside Trader routes (Phase 8)**.
- `resources/js/Pages/Leader/Trader/PaymentDetails.vue` — **read-only «Расписание» column + mobile block (Phase 8)**.
- `resources/js/Pages/PaymentDetail/Index.vue` — **schedule modals only when `isTraderView` (Phase 8)**; admin shares index table schedule column (Phase 7).
- `resources/js/Modals/PaymentDetail/PaymentDetailEditModal.vue` — **read-only `PaymentDetailScheduleStatus` when viewer cannot edit schedule (Phase 8)**.

## Domain Model

### Payment Detail Schedule

A schedule is a reusable trader-owned entity:

- belongs to `users.id`;
- has a unique `name` per user;
- contains one or more working rules;
- can be attached to many payment details;
- can be copied into a new schedule;
- cannot be deleted in the first version.

Recommended table:

- `payment_detail_schedules`
  - `id`
  - `user_id`
  - `name`
  - `created_at`
  - `updated_at`

Recommended indexes:

- unique index on `user_id, name`;
- index on `user_id`.

Do not add an `is_active` flag to the schedule. There is no global “disabled schedule” state. A payment detail either references a schedule or it does not.

### Schedule Intervals

Intervals define working time within one server-local day.

Recommended table:

- `payment_detail_schedule_intervals`
  - `id`
  - `payment_detail_schedule_id`
  - `day_of_week`
  - `starts_at`
  - `ends_at`
  - `source_type`
  - `created_at`
  - `updated_at`

Field semantics:

- `day_of_week` should use ISO weekday numbers: `1` Monday through `7` Sunday.
- `starts_at` and `ends_at` should be server-time `HH:mm` values.
- `source_type` should distinguish default-generated intervals from per-day overrides if the UI needs that distinction after loading. If the implementation stores only the effective expanded intervals per day, this field can be omitted.

Recommended indexes:

- index on `payment_detail_schedule_id, day_of_week`;
- index on `day_of_week, starts_at, ends_at` only if later query plans need it.

### Payment Detail Link

Add a nullable foreign key on `payment_details`:

- `payment_detail_schedule_id` nullable constrained to `payment_detail_schedules`;
- `null` means no schedule is applied;
- `not null` means schedule filtering applies.

Recommended index:

- index on `payment_detail_schedule_id`;
- consider compound query indexes only after checking real query plans, because selection is already constrained by user, activity, currency, gateway, limits, and locks.

The relation must be scoped by ownership in validation and service logic:

- a trader can attach only a schedule where `payment_detail_schedules.user_id = auth()->id()`;
- a payment detail owned by a trader can reference only that trader's schedule;
- admin/team leader read views should not mutate these relationships.

## Time Model

### Server Time Is the Source of Truth

Schedule rules are configured and evaluated in the server timezone.

The UI must make this explicit. Form labels and helper text should say that the entered time is server time, for example:

- `09:00-19:00 по времени сервера`;
- `Текущее время сервера: 14:32`;
- `В вашем часовом поясе это примерно 12:32`.

Do not silently convert editable `HH:mm` schedule fields into browser-local time. Hidden conversion would make users save unintended server-time values.

### ISO Is for Absolute Moments

The backend may return absolute moments as ISO strings, consistent with existing resource patterns that use `toISOString()` / `toIso8601String()`.

Use ISO strings for:

- current server moment;
- next start moment;
- current interval end moment;
- next interval start moment;
- status reference timestamps.

Do not use ISO strings as the persisted schedule rule format. Schedule rules are repeating wall-clock rules and should remain `day_of_week + HH:mm`.

### Interval Boundaries

Use the rule:

- start is inclusive;
- end is exclusive.

Examples:

- at `09:00`, interval `09:00-19:00` is working;
- at `18:59:59`, interval `09:00-19:00` is working;
- at `19:00`, interval `09:00-19:00` is no longer working.

This avoids double-matching adjacent intervals like `09:00-12:00` and `12:00-15:00`.

### No Overnight Intervals

Intervals must stay within one day:

- `starts_at < ends_at`;
- `22:00-06:00` is invalid;
- `00:00-23:59` is valid.

If the product later needs overnight support, implement it as a separate version with explicit split-day semantics.

## Schedule Rule Semantics

### Default Rule

The schedule editor should support a simple default mode:

- selected working days;
- shared start time;
- shared end time.

Example:

- Monday through Sunday;
- `09:00-19:00`.

This can be persisted either as expanded intervals per selected day or as a normalized parent rule plus intervals. For reliability and query simplicity, expanded effective intervals are recommended: each saved schedule has the final intervals for each day. The UI can still present them as default plus overrides.

### Per-Day Overrides

Per-day intervals are stronger than the default rule.

If a schedule has default working days Monday-Friday `09:00-19:00`, and Tuesday has override intervals `12:00-16:00`, Tuesday's effective schedule is only `12:00-16:00`.

Overrides replace the default for that day; they do not add to it.

### Multiple Intervals Per Day

A single day can contain multiple intervals:

- `09:00-12:00`;
- `15:00-19:00`.

Intervals must be sorted and non-overlapping after normalization.

Adjacent intervals can be allowed or normalized:

- `09:00-12:00` and `12:00-15:00` do not overlap under the exclusive-end rule;
- the UI may either keep them separate or merge them into `09:00-15:00`.

For a simpler first version, keep adjacent intervals as entered, but render them sorted.

### Empty Schedules

Do not allow saving a schedule with no working days and no working intervals.

If the UI presents a draft with no intervals, validation should fail with a clear message:

- `Добавьте хотя бы один рабочий день и интервал`.

## Availability Semantics

### Effective Availability

A payment detail is available for traffic only when all conditions pass:

- the owner is online;
- the owner has not stopped traffic;
- the owner is not banned or archived according to existing query rules;
- the payment detail is active;
- the payment detail is not archived;
- the payment detail passes existing currency, gateway, limit, amount, pending order, uniqueness, and device checks;
- if no schedule is attached, schedule availability passes;
- if a schedule is attached, current server time is inside one effective interval for the current server day.

This logic is centralized in Phase 2. All relevant queries should use the same definition via the shared helper below.

### Query Abstraction — **implemented (Phase 2)**

Reusable entry points:

- `PaymentDetail::scopeAvailableBySchedule(Builder $query, ?CarbonInterface $at = null)`;
- `PaymentDetailScheduleAvailabilityService::applyAvailableBySchedule(Builder $query, ?CarbonInterface $at = null)`.

The helper applies this logic:

- include payment details where `payment_detail_schedule_id` is null;
- or include payment details whose schedule has an interval for current ISO weekday where `starts_at <= current HH:mm:ss` and `ends_at > current HH:mm:ss`.

Do not duplicate raw `whereHas` schedule conditions independently in every controller. The risk of one counter drifting from order selection is high.

### Suggested SQL Shape

Implemented in `PaymentDetailScheduleAvailabilityService::applyAvailableBySchedule()`. At runtime:

- compute `$weekday = now()->isoWeekday()`;
- compute `$time = now()->format('H:i:s')`;
- filter:
  - no schedule attached;
  - or schedule interval exists for `$weekday` where start <= `$time` and end > `$time`.

Time comparison should use normalized fixed-width strings or database `TIME` columns. Avoid comparing user-facing formatted strings.

## Schedule Status Model

The UI needs status labels and timing details.

Status keys are defined in `App\Enums\PaymentDetailScheduleStatus` **(Phase 2)**:

- `not_configured`: no schedule attached;
- `working`: current server time is inside an interval;
- `day_off`: no intervals today;
- `starts_later`: first interval today is still ahead;
- `break_until`: an earlier interval ended and a later interval is ahead;
- `finished`: all today's intervals ended;
- `invalid`: schedule exists but has invalid or empty effective intervals, should not happen after validation.

Labels come from `PaymentDetailScheduleStatus::label()`:

- `Без расписания`;
- `Работает`;
- `Выходной`;
- `Скоро начнёт работу`;
- `Перерыв до HH:mm`;
- `Рабочее время закончилось`.

### Status Payload

For each payment detail resource, return enough data to render status without hidden timezone assumptions. Resolved by `PaymentDetailScheduleAvailabilityService::resolveStatus()` / `resolveStatusForPaymentDetail()` **(Phase 2)**:

- `schedule.id`;
- `schedule.name`;
- `schedule.server_timezone`;
- `schedule.server_now` (ISO string);
- `schedule.today_intervals`;
- `schedule.next_interval`;
- `schedule.current_interval`;
- `schedule.status`;
- `schedule.status_label`;

Example shape:

```json
{
  "schedule": {
    "id": 10,
    "name": "День",
    "server_timezone": "Asia/Bangkok",
    "server_now": "2026-05-22T16:30:00+07:00",
    "status": "break_until",
    "status_label": "Перерыв до 15:00",
    "today_intervals": [
      {
        "starts_at": "09:00",
        "ends_at": "12:00",
        "starts_at_iso": "2026-05-22T09:00:00+07:00",
        "ends_at_iso": "2026-05-22T12:00:00+07:00"
      },
      {
        "starts_at": "15:00",
        "ends_at": "19:00",
        "starts_at_iso": "2026-05-22T15:00:00+07:00",
        "ends_at_iso": "2026-05-22T19:00:00+07:00"
      }
    ],
    "current_interval": null,
    "next_interval": {
      "starts_at": "15:00",
      "ends_at": "19:00",
      "starts_at_iso": "2026-05-22T15:00:00+07:00",
      "ends_at_iso": "2026-05-22T19:00:00+07:00"
    }
  }
}
```

The backend should compute these fields in server time. The frontend may update relative text like “через 2 часа” locally using the ISO fields.

### Frontend Timer Reliability

Frontend status refresh must be simple and safe:

- use a single interval timer on the page, not one timer per row;
- recompute display labels from schedule payload and current browser time;
- when a boundary is crossed, either recompute from already available intervals or trigger a lightweight Inertia reload for `paymentDetails`;
- avoid aggressive polling;
- rely on backend filtering for actual traffic eligibility.

The frontend status is informational. The backend query remains authoritative.

## UI Specification

### Schedule Manager Modal

Add a dedicated modal for trader schedules.

Capabilities:

- list all schedules owned by the current trader;
- create schedule;
- edit schedule;
- copy schedule;
- show number of attached payment details if practical;
- warn before editing that changes apply to all attached payment details;
- no delete action in the first version.

The modal should be reachable from the payment detail page, and also from create/edit payment detail flows when the user wants to create a new schedule.

Recommended UI sections:

- schedule list on the left or top;
- editor form for selected schedule;
- name input;
- server timezone helper text;
- default working days selector;
- default start/end time;
- per-day overrides with add/remove interval controls;
- validation errors near affected fields;
- save button disabled while processing.

### Payment Detail Create/Edit Modal

Add a “Рабочее расписание” block.

Fields and actions:

- select an existing schedule;
- clear selected schedule;
- button `Создать расписание`;
- button `Управлять расписаниями` or `Редактировать расписания`;
- schedule preview when selected;
- current server time helper;
- text explaining that schedule time is server time.

When a new schedule is created from inside create/edit payment detail:

- close the schedule creation modal;
- refresh available schedules;
- automatically select the newly created schedule in the current payment detail form.

When editing an existing schedule from this path, make the shared impact clear:

- `Изменения применятся ко всем реквизитам, где используется это расписание`.

### Payment Detail Table

Add a schedule column near the active status column.

Suggested cell content:

- status badge or text;
- schedule name;
- today's interval or next interval;
- dash when no schedule applies.

Examples:

- `Работает`
  `День`
  `09:00-19:00`
- `Выходной`
  `Выходные`
  `-`
- `Перерыв до 15:00`
  `Смена`
  `15:00-19:00`
- `Рабочее время закончилось`
  `День`
  `09:00-19:00`
- `Без расписания`
  `-`

Working days in preview should be visually highlighted in blue. Days without intervals should be grey/neutral.

### Bulk Edit Modal

Extend existing bulk edit with schedule actions:

- `Применить расписание`;
- `Убрать расписание`.

When applying:

- require selected schedule;
- update selected/all/tag/without-tags scope according to existing bulk edit scope rules;
- attach schedule only if it belongs to the current trader.

When removing:

- set `payment_detail_schedule_id` to null;
- do not modify `is_active`.

Bulk schedule changes should work alongside current bulk edit fields only if the current modal architecture supports mixed field updates safely. If mixed updates complicate validation, keep schedule as a separate selected field/action inside the same modal.

## Backend API Specification

### Schedule List

Endpoint concept:

- `GET /payment-detail-schedules`

Response:

- current user's schedules;
- intervals;
- attached payment detail count if needed by UI;
- server timezone and server now.

Admin/team leader read flows may need route variants scoped to a trader, but mutation routes should remain trader-owned.

### Schedule Store

Endpoint concept:

- `POST /payment-detail-schedules`

Payload:

- `name`;
- default working days and default interval, or effective intervals;
- per-day overrides if the API accepts normalized editor state.

Validation:

- name required, string, unique per user;
- at least one effective interval;
- day values must be 1-7;
- times must be valid `HH:mm`;
- `starts_at < ends_at`;
- no overlapping intervals for the same day;
- no overnight intervals.

Response:

- created schedule resource;
- include enough fields for the current create/edit payment detail modal to select it immediately.

### Schedule Update

Endpoint concept:

- `PATCH /payment-detail-schedules/{schedule}`

Rules:

- only owner trader can update;
- admin/team leader cannot update in the first version;
- validate exactly like store;
- update schedule and intervals atomically in one transaction;
- replace interval set instead of attempting fragile partial interval updates.

Because schedules are shared, the UI should warn before submitting. Backend does not need special confirmation unless the project convention requires it.

### Schedule Copy

Endpoint concept:

- `POST /payment-detail-schedules/{schedule}/copy`

Payload:

- `name` for the new schedule.

Rules:

- only owner trader can copy;
- copied schedule is independent;
- copied intervals match the source schedule at copy time;
- new name must be unique per user.

### Payment Detail Store/Update

Extend payment detail create/update payload with:

- `payment_detail_schedule_id` nullable.

Validation:

- nullable;
- exists in `payment_detail_schedules`;
- belongs to current user.

When admin/team leader views a trader payment detail, the resource can include schedule information, but update should not allow changing schedule unless a future requirement explicitly grants that permission.

### Bulk Update

Extend bulk payload with a field/action for schedule:

- apply: `payment_detail_schedule_id = <id>`;
- remove: `payment_detail_schedule_id = null`.

Validation:

- scope rules remain the same as current bulk edit;
- schedule id required for apply;
- schedule id belongs to current user;
- remove action requires no schedule id.

Authorization:

- each affected detail still goes through existing access checks;
- schedule ownership must be checked once before applying and should match every updated detail's owner.

## Resource Specification

### Schedule Resource

Create a resource for schedules:

- `id`;
- `name`;
- `user_id`;
- `intervals`;
- `server_timezone`;
- `server_now`;
- `attached_payment_details_count` if loaded;
- `created_at`;
- `updated_at`.

For interval resources:

- `day_of_week`;
- `starts_at`;
- `ends_at`;
- optional display labels for day names.

### Payment Detail Resource Extension

Extend `PaymentDetailResource` with schedule fields when the relationship is loaded or when schedule data is needed for the index:

- `payment_detail_schedule_id`;
- `schedule`;
- `schedule_status`.

Use `->resolve()` when returning nested resource collections without pagination, following project convention.

Do not expose unrelated user data through schedule payloads.

## Query Integration Points

All traffic-available integration points below use `PaymentDetail::availableBySchedule()` (delegates to `PaymentDetailScheduleAvailabilityService::applyAvailableBySchedule`). **Implemented in Phase 5 (2026-05-24).**

### Order Selection — **implemented**

`FindAvailablePaymentDetail::queryPaymentDetails()` applies `->availableBySchedule()` immediately after `->active()`.

The filter runs in SQL (not after `first()` in PHP) so unavailable scheduled details are never selected under `FOR UPDATE SKIP LOCKED`.

Critical behavior:

- payment details with no schedule are eligible;
- payment details with schedule are eligible only during current server-time interval;
- existing `FOR UPDATE SKIP LOCKED` behavior remains unchanged.

### Enabled Cards Admin Page — **implemented**

`EnabledCardsController::trafficAvailablePaymentDetailsQuery()` centralizes:

- `whereNull('archived_at')`, `is_active`, user `is_online`, `availableBySchedule()`;
- optional filters: `detail_type`, payment gateway, `user_id`.

Used for:

- total enabled payment details count;
- active payment detail ids for pending order sums;
- free/potential limit calculations;
- min-amount group stats per currency.

### Merchant Main Page Availability — **implemented**

`MinAmountStatsService::activePaymentDetailsQuery()` includes `->availableBySchedule()` so merchant-facing min-amount statistics match order selection.

### Sidebar Active Detail Counts — **implemented**

`HandleInertiaRequests` trader (`active_details_trader_{id}`) and admin (`active_details_admin`) cached counts include `->availableBySchedule()`.

The 15-second cache is unchanged; counts may lag schedule boundary crossings by up to the cache TTL.

### Query audit (Phase 5)

| Location | Schedule filter | Rationale |
|----------|-----------------|-----------|
| `FindAvailablePaymentDetail` | Yes | Order traffic selection |
| `EnabledCardsController` | Yes | Admin “available cards” metrics |
| `MinAmountStatsService::activePaymentDetailsQuery` | Yes | Merchant availability stats |
| `HandleInertiaRequests` active detail counts | Yes | Sidebar traffic-available count |
| `PaymentDetailQueriesEloquent` (`filters.active`) | No | Manual `is_active` list filter only |
| `PaymentDetailEnabledPeriodService` | No | Historical enabled periods |
| `TraderAnalyticsController` enabled-detail charts | No | Point-in-time enabled-period analytics |
| `MainPageController` payment detail search | No | Historical order-linked search, not live eligibility |

## Validation Rules

### Name Validation

Rules:

- required;
- string;
- max length consistent with project conventions, for example 255;
- unique per `user_id`.

On update, ignore the current schedule id.

### Interval Validation

Normalize before validating:

- trim times;
- parse times as fixed `HH:mm`;
- sort intervals by day and start time.

Reject:

- invalid day numbers;
- empty interval list;
- missing start or end;
- start greater than or equal to end;
- overnight intervals;
- duplicate intervals if desired;
- overlapping intervals within the same day.

Overlap rule for a sorted list:

- interval B overlaps interval A if `B.starts_at < A.ends_at`.

Because end is exclusive, `B.starts_at === A.ends_at` is not an overlap.

### Ownership Validation

Every schedule mutation is scoped to `auth()->id()`.

Every payment detail schedule assignment must satisfy:

- payment detail owner id equals current user id;
- schedule owner id equals current user id.

Admin/team leader read access should not weaken mutation rules.

## Data Consistency

### Transactions

Use transactions for:

- creating schedule with intervals;
- updating schedule and replacing intervals;
- copying schedule and intervals;
- bulk assigning/removing schedules.

### Avoid Partial Interval Updates

For update, replace the schedule's intervals in one transaction:

1. validate normalized effective interval set;
2. lock the schedule row if needed;
3. delete existing interval rows for the schedule;
4. insert normalized rows;
5. commit.

This avoids stale intervals and complicated per-row diff bugs.

### No Existing Period Reuse

Do not use `payment_detail_enabled_periods` for schedule rules.

That table represents historical effective enabled periods and is tied to `is_active`, online state, traffic stop, and archive state. Work schedules are forward-looking rules for eligibility and need separate storage.

## Permissions

### Trader

Can:

- list own schedules;
- create own schedule;
- edit own schedule;
- copy own schedule;
- attach own schedule to own payment detail;
- remove schedule from own payment detail;
- bulk attach/remove own schedules.

Cannot:

- access another trader's schedules;
- attach another trader's schedule.

### Team Leader

Can:

- view attached schedule information for traders they are allowed to see;
- see schedule status in payment detail views if the page already exposes those payment details.

Cannot:

- create/edit/copy schedules for traders;
- attach/remove schedules from trader payment details.

### Admin

Can:

- view schedule information attached to trader payment details.

Cannot in first version:

- edit trader schedules;
- attach/remove trader schedules.

This intentionally avoids unexpected mass changes by an admin to a shared trader-owned schedule.

## Frontend Components

Recommended components:

- `PaymentDetailScheduleSelect.vue`: select/clear schedule and open manager/create modal.
- `PaymentDetailScheduleManagerModal.vue`: list and edit schedules **(Phase 6 — implemented)**.
- `PaymentDetailScheduleForm.vue`: reusable create/edit form **(Phase 6 — implemented)**.
- `PaymentDetailSchedulePreview.vue`: render days, intervals, server-time helper.
- `PaymentDetailScheduleStatus.vue`: render table status cell and relative labels **(Phase 7 — implemented)**.

Keep Vue `script setup` before `template`, follow existing modal patterns, and do not add component-local styles.

Use existing DaisyUI/Tailwind utility classes and existing modal/select/input components where possible.

## Status Calculation Details

### Backend Calculation

Implemented in `PaymentDetailScheduleAvailabilityService::resolveStatus()`. Uses server time:

1. Get today's effective intervals for `now()->isoWeekday()`.
2. If no intervals, status is `day_off`.
3. Find interval where `starts_at <= nowTime < ends_at`.
4. If found, status is `working`.
5. Find the first interval where `starts_at > nowTime`.
6. If found and at least one earlier interval ended, status is `break_until`.
7. If found and no earlier interval ended, status is `starts_later`.
8. Otherwise status is `finished`.

### Frontend Display

Frontend display should prefer backend status key and use ISO moments for relative text:

- `working`: show current interval and optionally “до HH:mm”;
- `starts_later`: show “Начнёт через …” or “Скоро начнёт работу”;
- `break_until`: show “Перерыв до HH:mm”;
- `finished`: show today's intervals if any;
- `day_off`: show dash for interval.

The relative timer should degrade gracefully:

- if ISO parsing fails, show static server-time label;
- if browser clock is wrong, backend selection is still authoritative;
- reload on page navigation or table refresh will restore correct state.

## Implementation Phases

### Phase 0: Discovery and Design Lock

Goals:

- identify all payment detail create/edit/bulk routes and modal data flows;
- identify all active/available detail queries;
- confirm how payment detail index data is loaded and refreshed;
- confirm current route names and modal store conventions.

Deliverables:

- final list of backend files to touch;
- final list of frontend files/components to touch;
- confirmed naming for tables, models, routes, and props.

Notes:

- do not modify `PaymentDetailEnabledPeriod` logic except if a later requirement explicitly asks to include schedule in historical effective-enabled stats;
- document which “active” queries are schedule-aware and which remain manual-active only.

### Phase 1: Database and Models — **Done** (2026-05-24)

Goals:

- add schedule tables;
- add nullable schedule foreign key to payment details;
- add Eloquent models and relationships.

Backend tasks:

- create `PaymentDetailSchedule` model;
- create `PaymentDetailScheduleInterval` model;
- add `PaymentDetail::schedule()` relation;
- add `PaymentDetailSchedule::intervals()` relation;
- add `PaymentDetailSchedule::paymentDetails()` relation;
- add fillable/casts consistently with project style;
- add migration for schedule tables;
- add migration for `payment_details.payment_detail_schedule_id`.

Acceptance:

- schedules can be represented independently of payment details;
- payment details can reference no schedule or one schedule;
- schedule names are unique per user;
- intervals are linked to schedules and constrained on delete.

### Phase 2: Domain Service and Validation — **Done** (2026-05-24)

Goals:

- centralize schedule normalization, validation, and status calculation;
- avoid duplicating time logic across controllers and components.

Backend tasks (all complete):

- create Form Requests for schedule store/update/copy;
- create a service/action for schedule create/update/copy;
- create an interval normalization helper;
- create overlap validation;
- create status resolver for server-time status payload;
- create query helper/scope for availability filtering.

Acceptance (verified in code):

- invalid intervals are rejected;
- empty schedules are rejected;
- overlapping same-day intervals are rejected;
- overnight intervals are rejected;
- editing a schedule replaces intervals atomically;
- status resolver returns stable results for working, day off, starts later, break, and finished cases.

### Phase 3: Schedule CRUD API — **Done** (2026-05-24)

Goals:

- expose trader-owned schedule operations to the frontend.

Backend tasks:

- add routes for list/store/update/copy;
- add controller with thin methods;
- add schedule resource and interval resource;
- return server timezone and server now where useful;
- enforce owner-only mutations;
- expose read-only schedule data to admin/team leader views only through existing payment detail resources.

Acceptance:

- trader can create schedule;
- trader can edit own schedule;
- trader can copy own schedule under a unique name;
- trader cannot access or mutate another trader's schedule;
- admin/team leader cannot mutate schedules in first version.

Route changes require:

- `php artisan optimize`;
- `php artisan ziggy:generate resources/js/ziggy-routes.js`.

### Phase 4: Payment Detail Assignment — **Done** (2026-05-24)

Goals:

- attach/remove schedules during payment detail create/edit;
- attach/remove schedules through bulk edit.

Backend tasks:

- extend payment detail store/update DTO/request/service flow with nullable schedule id;
- validate schedule ownership;
- add schedule field to `PaymentDetailResource`;
- load schedule relation where the index and modals need it;
- extend `BulkUpdateRequest` with schedule action/field;
- update bulk update controller payload builder.

Frontend tasks:

- add schedule select and clear action to create modal;
- add schedule select and clear action to edit modal;
- refresh schedule list after creating from inside a payment detail modal;
- auto-select newly created schedule;
- add bulk apply/remove UI.

Acceptance:

- creating a payment detail can attach a schedule;
- editing a payment detail can change or remove schedule;
- creating a schedule from the payment detail modal auto-selects it;
- bulk edit can apply schedule;
- bulk edit can remove schedule;
- none of these actions changes `is_active`.

### Phase 5: Traffic Selection and Availability Queries — **Done** (2026-05-24)

Goals:

- make schedule restrictions authoritative in backend selection and availability counts.

Backend tasks (all complete):

- `FindAvailablePaymentDetail::queryPaymentDetails()` — `->availableBySchedule()` after `->active()`;
- `EnabledCardsController::trafficAvailablePaymentDetailsQuery()` — shared builder for all enabled-card queries;
- `MinAmountStatsService::activePaymentDetailsQuery()` — `->availableBySchedule()`;
- `HandleInertiaRequests` — trader/admin sidebar counts;
- query audit documented in [Query Integration Points](#query-integration-points).

Acceptance (verified in code):

- scheduled payment detail receives traffic only inside schedule intervals;
- unscheduled payment detail behaves as before;
- disabled payment detail never receives traffic even inside schedule;
- offline trader never receives traffic even inside schedule;
- enabled cards stats match schedule-aware availability;
- sidebar active counts become schedule-aware after existing cache refresh.

### Phase 6: Schedule Manager UI — **Done (2026-05-24)**

Goals:

- provide full trader schedule management without leaving payment detail workflows.

**Implemented (frontend only, reuses Phase 3 API):**

- `PaymentDetailScheduleManagerModal.vue` — schedule list, create/edit form, copy-by-name;
- `PaymentDetailScheduleForm.vue` + `usePaymentDetailScheduleEditor.js` — default weekdays/times, per-day overrides, multiple intervals, local validation;
- entry: payment detail index (trader) «Расписания работы», `PaymentDetailScheduleField` «Управлять расписаниями»;
- `ConfirmModal` before save when `payment_details_count > 0`;
- quick-create modal unchanged for fast Mon–Fri default.

Acceptance (met in code):

- trader can manage schedule list from payment detail page;
- trader can create/edit/copy schedules;
- schedule form prevents obvious client-side invalid intervals before submit;
- backend validation errors are shown next to relevant fields;
- no delete action is present.

### Phase 7: Payment Detail Table Status — **Done (2026-05-24)**

Goals:

- make schedule state visible and understandable in the payment detail table.

**Backend:** no changes — index already loads `schedule.intervals` and `PaymentDetailResource` exposes full `schedule` payload (Phase 4).

**Implemented (frontend):**

- `resources/js/utils/paymentDetailScheduleStatus.js` — `resolvePaymentDetailScheduleDisplay()`, badge classes
- `resources/js/composables/usePaymentDetailScheduleTableTick.js` — 30s tick, `server_now` offset, midnight `router.reload({ only: ['paymentDetails'] })`
- `resources/js/Components/PaymentDetail/PaymentDetailScheduleStatus.vue` — badge + schedule name + interval line
- `resources/js/Pages/PaymentDetail/Index.vue` — column «Расписание» (desktop, between «Лимиты» and «Статус»); mobile card block

Acceptance (met in code):

- status labels are understandable;
- break between intervals displays as `Перерыв до HH:mm`;
- table clearly distinguishes no schedule, day off, starts later, working, break, and finished;
- displayed interval times remain server-time values from API payload.

### Phase 8: Role Read Views

**Status:** Done (2026-05-24). See [Phase 8 artifacts](#phase-8-artifacts-implemented) below.

Goals:

- let Team Leaders and admins understand trader payment detail schedules without mutation access.

Tasks (completed):

- include schedule name/status in pages where Team Leaders/admins already view trader payment details;
- avoid create/edit controls for schedules in those roles;
- ensure policy/controller restrictions prevent mutation.

Acceptance (met):

- Team Leader can see schedule state on `leader.traders.payment-details.index` (`Leader/Trader/PaymentDetails.vue`);
- admin can see schedule state on `admin.payment-details.index` (shared `PaymentDetail/Index.vue` column) and read-only block in `PaymentDetailEditModal`;
- neither role can edit trader schedules: `PaymentDetailScheduleController::ensureTrader()`, `UpdateRequest::canUpdateSchedule()`, schedule modals gated by `isTraderView`, `BulkUpdateRequest::authorize()` for bulk schedule fields.

**Out of scope (v1):** Analyst `users.payment-details.index`; dedicated non-trader schedule manager pages; schedule list API for admin/Team Leader.

### Phase 9: Verification

Goals:

- verify time-sensitive behavior carefully and avoid hidden timezone surprises.

Manual verification checklist:

- no schedule attached: payment detail behaves as before;
- schedule attached and current time inside interval: detail is eligible;
- schedule attached and current time outside interval: detail is not eligible;
- inactive detail inside schedule is still not eligible;
- online off / stop traffic on still blocks eligibility;
- interval start is inclusive;
- interval end is exclusive;
- same-day multiple intervals work;
- break between intervals shows `Перерыв до ...`;
- day without intervals shows `Выходной`;
- after all intervals, status shows `Рабочее время закончилось`;
- default interval applies to selected days;
- per-day override replaces default for that day;
- overlapping intervals are rejected;
- overnight intervals are rejected;
- schedule copy creates independent schedule;
- schedule edit affects all attached details;
- bulk apply attaches schedule to selected scope;
- bulk remove clears schedule from selected scope;
- admin/team leader read views show schedules but no mutation controls;
- enabled card stats and sidebar counts become schedule-aware after cache refresh.

Programmatic verification should focus on the pure schedule availability/status resolver and request validation. Run tests only when explicitly requested according to the project's current working rules.

## Implementation Status

| Phase | Status | Notes |
|-------|--------|-------|
| 0 — Discovery and design lock | **Done** (wiki plan, 2026-05-22) | Spec and file anchors documented in this article |
| 1 — Database and models | **Done** (2026-05-24) | Migrations, `PaymentDetailSchedule`, `PaymentDetailScheduleInterval`, `PaymentDetail::schedule()` |
| 2 — Domain service and validation | **Done** (2026-05-24) | Normalizer, CRUD service, status/availability services, Form Requests, `scopeAvailableBySchedule` |
| 3 — Schedule CRUD API | **Done** (2026-05-24) | `PaymentDetailScheduleController`, resources, `payment-detail-schedules.*` routes, Ziggy |
| 4 — Payment detail assignment | **Done** (2026-05-24) | Store/update/bulk attach-remove; `PaymentDetailResource` schedule payload; trader UI field + quick create |
| 5 — Traffic selection and availability queries | **Done** (2026-05-24) | `FindAvailablePaymentDetail`, `EnabledCardsController`, `MinAmountStatsService`, `HandleInertiaRequests` |
| 6 — Schedule manager UI | **Done** (2026-05-24) | Manager modal, editor composable, form with overrides; no backend changes |
| 7 — Payment detail table status | **Done** (2026-05-24) | Index + mobile UI; client tick; backend payload unchanged (Phase 4) |
| 8 — Role read views | **Done** (2026-05-24) | Admin index + TL trader requisites; read-only edit modal; query eager-load |
| 9 — Verification | Pending | Manual checklist and targeted tests when requested |

### Phase 1 artifacts (implemented)

**Migrations** (2026-05-24):

- `database/migrations/2026_05_24_130610_create_payment_detail_schedules_table.php` — `payment_detail_schedules` + `payment_detail_schedule_intervals` (short FK `pdsi_schedule_fk` for MySQL identifier limit)
- `database/migrations/2026_05_24_130620_add_payment_detail_schedule_id_to_payment_details_table.php` — nullable FK `pd_schedule_fk`, index `pd_schedule_id_idx`

**Models:**

- `app/Models/PaymentDetailSchedule.php` — `user()`, `intervals()`, `paymentDetails()`; strict types
- `app/Models/PaymentDetailScheduleInterval.php` — `schedule()`; `day_of_week` cast to integer; strict types
- `app/Models/PaymentDetail.php` — `payment_detail_schedule_id` in `$fillable`; `schedule()` `belongsTo`

**Schema choices locked in Phase 1:**

- Intervals stored as expanded rows (`day_of_week` + `time` `starts_at`/`ends_at`); no `source_type` column
- Schedule names unique per `user_id` at database level
- Detaching schedule from payment detail: set `payment_detail_schedule_id` null; schedule row delete (not in v1 product) would null FK via `nullOnDelete`

**Not changed in Phase 1:**

- Traffic selection, availability counters, API routes, Form Requests, services
- `PaymentDetailResource`, Vue modals/pages, bulk edit
- `PaymentDetailEnabledPeriod` / enabled-period statistics

### Phase 2 artifacts (implemented)

**Enum:**

- `app/Enums/PaymentDetailScheduleStatus.php` — status keys + Russian `label()`

**DTOs:**

- `app/DTO/PaymentDetailSchedule/PaymentDetailScheduleIntervalData.php`
- `app/DTO/PaymentDetailSchedule/PaymentDetailScheduleUpsertDTO.php`
- `app/DTO/PaymentDetailSchedule/PaymentDetailScheduleCopyDTO.php`

**Services:**

- `app/Services/PaymentDetail/PaymentDetailScheduleIntervalNormalizer.php` — parse, sort, overlap/overnight validation
- `app/Services/PaymentDetail/PaymentDetailScheduleService.php` — create, update, copy (atomic interval replace)
- `app/Services/PaymentDetail/PaymentDetailScheduleAvailabilityService.php` — `applyAvailableBySchedule`, status payload resolver

**Validation:**

- `app/Rules/PaymentDetailScheduleIntervals.php`
- `app/Http/Requests/PaymentDetailSchedule/StoreRequest.php`
- `app/Http/Requests/PaymentDetailSchedule/UpdateRequest.php`
- `app/Http/Requests/PaymentDetailSchedule/CopyRequest.php`

**Model:**

- `app/Models/PaymentDetail.php` — `scopeAvailableBySchedule()`

**Domain service API (Phase 2; HTTP routes in Phase 3):**

| Component | Method | Purpose |
|-----------|--------|---------|
| `PaymentDetailScheduleService` | `create($user_id, PaymentDetailScheduleUpsertDTO)` | Create schedule + intervals |
| `PaymentDetailScheduleService` | `update($schedule, PaymentDetailScheduleUpsertDTO)` | Update name, replace intervals |
| `PaymentDetailScheduleService` | `copy($schedule, PaymentDetailScheduleCopyDTO)` | Independent copy |
| `PaymentDetailScheduleAvailabilityService` | `applyAvailableBySchedule($query, $at?)` | SQL availability filter |
| `PaymentDetailScheduleAvailabilityService` | `resolveStatus($schedule, $at?)` | Status payload for UI |
| `PaymentDetailScheduleAvailabilityService` | `resolveStatusForPaymentDetail($detail, $at?)` | Null when no schedule attached |
| `PaymentDetailScheduleIntervalNormalizer` | `normalize($intervals)` | Parse/validate/sort intervals |

**Schedule store/update payload (Form Request):**

```json
{
  "name": "День",
  "intervals": [
    { "day_of_week": 1, "starts_at": "09:00", "ends_at": "19:00" }
  ]
}
```

**Not changed in Phase 2:**

- Schedule CRUD routes, controller, API resources (delivered in Phase 3)
- Payment detail assignment in store/update/bulk
- Traffic selection and availability counters integration
- Vue UI

### Phase 3 artifacts (implemented)

**Routes** (`routes/web.php`, `role:Trader|Super Admin` group):

| Method | URI | Name |
|--------|-----|------|
| GET | `/payment-detail-schedules` | `payment-detail-schedules.index` |
| POST | `/payment-detail-schedules` | `payment-detail-schedules.store` |
| PATCH | `/payment-detail-schedules/{paymentDetailSchedule}` | `payment-detail-schedules.update` |
| POST | `/payment-detail-schedules/{paymentDetailSchedule}/copy` | `payment-detail-schedules.copy` |

**Controller:**

- `app/Http/Controllers/PaymentDetailScheduleController.php` — `index`, `store`, `update`, `copy`; `ensureTrader()` + `ensureOwner()` (same pattern as `PaymentDetailTagController`)

**Resources:**

- `app/Http/Resources/PaymentDetailScheduleResource.php` — status via `resolveStatus()`, `payment_details_count`, `intervals`, ISO timestamps
- `app/Http/Resources/PaymentDetailScheduleIntervalResource.php` — `id`, `day_of_week`, `starts_at`, `ends_at` (`HH:mm`)

**JSON responses:**

- Index: `{ success, data: { server_timezone, server_now, schedules[] } }`
- Store/update/copy: `{ success, data: <schedule resource> }`

**Authorization:**

- Mutations only when `isRouteFor('Trader')` and schedule `user_id === auth()->id()`
- No delete route (v1)
- Admin/Team Leader schedule read deferred to payment detail resources (Phases 4/8)

**Not changed in Phase 3:**

- `PaymentDetailResource` / payment detail store-update-bulk assignment (delivered in Phase 4)
- Vue schedule manager and table column (Phases 6–7)

### Phase 4 artifacts (implemented)

**Validation:**

- `app/Rules/OwnedPaymentDetailSchedule.php`

**Payment detail requests:**

- `StoreRequest` / `UpdateRequest` — nullable `payment_detail_schedule_id`; update rules only when auth user owns the detail
- `BulkUpdateRequest` — fields `schedule_apply`, `schedule_remove`; schedule id required for apply

**DTOs and service:**

- `PaymentDetailCreateDTO`, `PaymentDetailUpdateDTO` — `payment_detail_schedule_id`; update DTO has `updates_schedule` flag
- `PaymentDetailService` — create persists FK; update changes FK only when `updates_schedule`

**Controller and queries:**

- `PaymentDetailController` — owner-only schedule on update; bulk payload builder; `schedule.intervals` eager load on index/show
- `PaymentDetailQueriesEloquent::paginateForUser()` — `schedule.intervals` in default `with()`

**Resource:**

- `PaymentDetailResource` — `payment_detail_schedule_id`, `schedule` from `resolveStatusForPaymentDetail()`

**Frontend:**

- `resources/js/composables/usePaymentDetailSchedules.js`
- `resources/js/Components/PaymentDetail/PaymentDetailScheduleField.vue`
- `resources/js/Modals/PaymentDetailSchedule/PaymentDetailScheduleQuickCreateModal.vue` — default Mon–Fri 09:00–19:00 server time
- Integrated into create/edit/bulk modals; modal store entry `paymentDetailScheduleQuickCreate`

**Authorization notes:**

- Admin editing another user's payment detail cannot change schedule assignment (schedule fields omitted from update DTO path)
- Bulk schedule changes require trader ownership of both details and schedule

**Not changed in Phase 4:**

- Traffic selection (`FindAvailablePaymentDetail`) and availability counters (delivered in Phase 5)
- Full schedule manager with per-day override editor (Phase 6)
- Admin/Team Leader read-only schedule surfaces (delivered in Phase 8)

### Phase 5 artifacts (implemented)

**Traffic selection:**

- `app/Services/Order/Features/OrderDetailProvider/Classes/FindAvailablePaymentDetail.php` — `->availableBySchedule()` in `queryPaymentDetails()` after `->active()`

**Availability counters:**

- `app/Http/Controllers/Admin/EnabledCardsController.php` — `trafficAvailablePaymentDetailsQuery()` applies schedule filter to all enabled-card statistics queries
- `app/Services/EnabledCards/MinAmountStatsService.php` — `activePaymentDetailsQuery()` includes `->availableBySchedule()`
- `app/Http/Middleware/HandleInertiaRequests.php` — trader/admin `active_details_*` cached counts include `->availableBySchedule()`

**Audit: intentionally not schedule-filtered**

- `PaymentDetailQueriesEloquent` list filters (`active` filter = manual `is_active` only)
- `PaymentDetailEnabledPeriodService` — historical periods
- `TraderAnalyticsController` enabled-detail charts — enabled-period based

**Not changed in Phase 5:**

- Schedule manager UI (delivered in Phase 6)
- Index table schedule column UI (delivered in Phase 7)
- Admin/Team Leader read-only schedule surfaces (delivered in Phase 8)

### Phase 6 artifacts (implemented)

**Editor composable:**

- `resources/js/composables/usePaymentDetailScheduleEditor.js` — `intervalsToEditorState`, `editorStateToIntervals`, `validateEditorStateLocally`, weekday toggles and override interval helpers

**UI:**

- `resources/js/Components/PaymentDetail/PaymentDetailScheduleForm.vue`
- `resources/js/Modals/PaymentDetailSchedule/PaymentDetailScheduleManagerModal.vue`

**Integration:**

- `resources/js/store/modal.js` — `paymentDetailScheduleManager`, `openPaymentDetailScheduleManagerModal()`
- `resources/js/Components/PaymentDetail/PaymentDetailScheduleField.vue` — «Управлять расписаниями»; list refresh on modal close
- `resources/js/Pages/PaymentDetail/Index.vue` — trader dropdown «Расписания работы»

**Behavior:**

- Create/update send `{ name, intervals }` to existing store/update routes
- Copy sends `{ name }` to copy route; intervals duplicated server-side
- Edit save with attached payment details shows confirmation warning
- Per-day override replaces default for that ISO weekday; multiple non-overlapping intervals per day supported in UI

**Not changed in Phase 6:**

- Backend PHP (no new routes or migrations)
- Index table schedule status column (delivered in Phase 7)
- Admin/Team Leader read-only schedule surfaces (delivered in Phase 8)
- Verification checklist execution (Phase 9)

### Phase 7 artifacts (implemented)

**Client status display:**

- `resources/js/utils/paymentDetailScheduleStatus.js` — mirrors backend interval semantics (inclusive start, exclusive end); `invalid` trusts API `schedule.status`
- `resources/js/composables/usePaymentDetailScheduleTableTick.js` — one `setInterval` per index page; partial reload on server date rollover

**UI:**

- `resources/js/Components/PaymentDetail/PaymentDetailScheduleStatus.vue`
- `resources/js/Pages/PaymentDetail/Index.vue` — desktop column + mobile «Расписание» section; visible to trader and admin on shared index

**Behavior:**

- Frontend status is informational; selection still uses `availableBySchedule()` (Phase 5)
- Between Inertia reloads, labels refresh every 30s from `today_intervals` + server offset
- After server midnight, `paymentDetails` prop reload preserves scroll

**Not changed in Phase 7:**

- Backend PHP, routes, migrations
- Team Leader trader requisites schedule column (delivered in Phase 8)
- Verification checklist (Phase 9)

### Phase 8 artifacts (implemented)

**Queries:**

- `app/Queries/Eloquent/PaymentDetailQueriesEloquent.php` — `paginateForAdmin()` and `paginateForTeamLeader()` eager-load `schedule.intervals`

**Authorization:**

- `app/Http/Requests/PaymentDetail/BulkUpdateRequest.php` — `authorize()` blocks schedule bulk fields outside Trader routes

**UI:**

- `resources/js/Pages/Leader/Trader/PaymentDetails.vue` — «Расписание» column + mobile block; `usePaymentDetailScheduleTableTick`
- `resources/js/Pages/PaymentDetail/Index.vue` — schedule modals only when `isTraderView`
- `resources/js/Modals/PaymentDetail/PaymentDetailEditModal.vue` — read-only `PaymentDetailScheduleStatus` for admin viewing trader details

**Not changed in Phase 8:**

- Analyst user payment details page
- Non-trader schedule list/manager API
- Verification checklist (Phase 9)

## Rollout Notes

This feature changes traffic eligibility, so implementation should be conservative:

- schedule filtering is live in order selection and availability counters (Phase 5); traders can manage schedules in the manager UI (Phase 6), assign via modals/bulk (Phase 4), and see per-detail schedule state in the index table/cards (Phase 7); admin and Team Leader see schedule status read-only on payment detail pages (Phase 8);
- avoid hidden timezone conversions in editable inputs;
- prefer server-side authority for selection;
- keep frontend status display informational;
- keep old behavior for unscheduled details;
- leave existing 15-second active detail cache unchanged unless a later issue proves it harmful.

## Open Implementation Choices

The following choices can be made during implementation without changing product behavior:

- whether to persist effective expanded intervals only, or persist default rule plus overrides and derive effective intervals — **Phase 1 stores expanded intervals only** (no `source_type`);
- whether adjacent intervals should be merged or preserved;
- whether the table status reloads at interval boundaries or only updates text locally until the next normal Inertia refresh — **Phase 7 default:** local recompute every 30s from `today_intervals`; partial Inertia reload on server calendar date change; full navigation refresh still authoritative;
- exact route names and component names, as long as they follow project conventions.

The recommended default is to persist effective expanded intervals because it makes traffic filtering simple and reliable.
