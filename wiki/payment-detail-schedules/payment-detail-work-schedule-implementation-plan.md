# Payment Detail Work Schedule Implementation Plan

> Sources: User conversation, 2026-05-22; repository exploration, 2026-05-22
> Raw: [Payment Detail Work Schedule Requirements](../../raw/payment-detail-schedules/2026-05-22-payment-detail-work-schedule-requirements.md)
> Updated: 2026-05-22

## Overview

Payment detail work schedules add a server-time availability layer on top of the existing trader online state and payment detail active state. A payment detail still remains manually enabled or disabled by the trader, but when a schedule is attached to it, traffic can use that detail only inside the schedule's configured working intervals. Schedules are named trader-owned entities that may be attached to many payment details; editing a schedule affects every attached detail.

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

- `app/Models/PaymentDetail.php` stores the manual payment detail state and should receive the schedule relationship.
- `app/Services/Order/Features/OrderDetailProvider/Classes/FindAvailablePaymentDetail.php` is the critical traffic selection query for incoming orders.
- `app/Http/Resources/PaymentDetailResource.php` feeds `resources/js/Pages/PaymentDetail/Index.vue` and the create/edit modals.
- `app/Http/Requests/PaymentDetail/StoreRequest.php` and `app/Http/Requests/PaymentDetail/UpdateRequest.php` validate payment detail form payloads.
- `app/Http/Requests/PaymentDetail/BulkUpdateRequest.php` controls existing bulk edit fields.
- `resources/js/Modals/PaymentDetail/PaymentDetailCreateModal.vue` and `resources/js/Modals/PaymentDetail/PaymentDetailEditModal.vue` need schedule selection UI.
- `resources/js/Modals/PaymentDetail/PaymentDetailBulkEditModal.vue` needs attach/remove schedule actions.
- `resources/js/Pages/PaymentDetail/Index.vue` needs the schedule column.
- `app/Http/Middleware/HandleInertiaRequests.php` currently counts active details for the sidebar with 15-second cache.
- `app/Http/Controllers/Admin/EnabledCardsController.php` and `app/Services/EnabledCards/MinAmountStatsService.php` count available cards/details for admin and merchant-facing availability widgets.
- `app/Models/PaymentDetailEnabledPeriod.php` and `app/Services/PaymentDetail/PaymentDetailEnabledPeriodService.php` track historical effective enabled periods and must not be reused as schedule storage.

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

This logic should be centralized so all relevant queries use the same definition.

### Recommended Query Abstraction

Add a reusable local scope or query helper for schedule availability, for example:

- `PaymentDetail::scopeAvailableBySchedule(Builder $query, ?CarbonInterface $at = null)`;
- or `PaymentDetailScheduleAvailabilityService::applyAvailableBySchedule(Builder $query, CarbonInterface $at)`.

The helper should apply this logic:

- include payment details where `payment_detail_schedule_id` is null;
- or include payment details whose schedule has an interval for current ISO weekday where `starts_at <= current HH:mm` and `ends_at > current HH:mm`.

Do not duplicate raw `whereHas` schedule conditions independently in every controller. The risk of one counter drifting from order selection is high.

### Suggested SQL Shape

At runtime:

- compute `$weekday = now()->isoWeekday()`;
- compute `$time = now()->format('H:i')` or a safer comparable `HH:MM:SS` value;
- filter:
  - no schedule attached;
  - or schedule interval exists for `$weekday` where start <= `$time` and end > `$time`.

Time comparison should use normalized fixed-width strings or database `TIME` columns. Avoid comparing user-facing formatted strings.

## Schedule Status Model

The UI needs status labels and timing details.

Recommended status keys:

- `not_configured`: no schedule attached;
- `working`: current server time is inside an interval;
- `day_off`: no intervals today;
- `starts_later`: first interval today is still ahead;
- `break_until`: an earlier interval ended and a later interval is ahead;
- `finished`: all today's intervals ended;
- `invalid`: schedule exists but has invalid or empty effective intervals, should not happen after validation.

Recommended labels:

- `Без расписания`;
- `Работает`;
- `Выходной`;
- `Скоро начнёт работу`;
- `Перерыв до HH:mm`;
- `Рабочее время закончилось`.

### Status Payload

For each payment detail resource, return enough data to render status without hidden timezone assumptions:

- `schedule.id`;
- `schedule.name`;
- `schedule.server_timezone`;
- `schedule.server_now_iso`;
- `schedule.today_intervals`;
- `schedule.next_interval`;
- `schedule.current_interval`;
- `schedule.status`;

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

### Order Selection

`FindAvailablePaymentDetail::queryPaymentDetails()` must apply schedule availability close to the existing `->active()` condition.

The schedule filter should not run after `first()` in PHP; it must be part of the SQL query to avoid selecting an unavailable detail and then falling through.

Critical behavior:

- payment details with no schedule are eligible;
- payment details with schedule are eligible only during current server-time interval;
- existing `FOR UPDATE SKIP LOCKED` behavior remains unchanged.

### Enabled Cards Admin Page

`Admin\EnabledCardsController` has multiple active detail queries. Each query that claims to represent active/enabled/available payment details should use the shared schedule availability helper.

This includes:

- total enabled payment details count;
- active payment detail ids used for pending order sums;
- free/potential limit calculations;
- filtered views by detail type, gateway, and user.

### Merchant Main Page Availability

`EnabledCards\MinAmountStatsService::activePaymentDetailsQuery()` should use schedule availability so merchant-facing available card statistics match actual selection behavior.

### Sidebar Active Detail Counts

`HandleInertiaRequests` active detail counters should use the same schedule availability helper.

The existing 15-second cache can remain. It is acceptable for counts to lag schedule edits or boundary changes by up to the existing cache lifetime.

### Other Active Detail Queries

Search for active detail queries using combinations of:

- `PaymentDetail::query()`;
- `where('is_active', true)`;
- `whereNull('archived_at')`;
- `whereRelation('user', 'is_online', true)`;
- `active()`.

For each query, decide whether it means:

- manually active, regardless of schedule;
- traffic-available, schedule must apply.

Only traffic-available queries should receive schedule filtering.

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
- `PaymentDetailScheduleManagerModal.vue`: list and edit schedules.
- `PaymentDetailScheduleForm.vue`: reusable create/edit/copy form.
- `PaymentDetailSchedulePreview.vue`: render days, intervals, server-time helper.
- `PaymentDetailScheduleStatus.vue`: render table status cell and relative labels.

Keep Vue `script setup` before `template`, follow existing modal patterns, and do not add component-local styles.

Use existing DaisyUI/Tailwind utility classes and existing modal/select/input components where possible.

## Status Calculation Details

### Backend Calculation

Backend status calculation should use server time:

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

### Phase 1: Database and Models

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

### Phase 2: Domain Service and Validation

Goals:

- centralize schedule normalization, validation, and status calculation;
- avoid duplicating time logic across controllers and components.

Backend tasks:

- create Form Requests for schedule store/update/copy;
- create a service/action for schedule create/update/copy;
- create an interval normalization helper;
- create overlap validation;
- create status resolver for server-time status payload;
- create query helper/scope for availability filtering.

Acceptance:

- invalid intervals are rejected;
- empty schedules are rejected;
- overlapping same-day intervals are rejected;
- overnight intervals are rejected;
- editing a schedule replaces intervals atomically;
- status resolver returns stable results for working, day off, starts later, break, and finished cases.

### Phase 3: Schedule CRUD API

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

### Phase 4: Payment Detail Assignment

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

### Phase 5: Traffic Selection and Availability Queries

Goals:

- make schedule restrictions authoritative in backend selection and availability counts.

Backend tasks:

- apply shared schedule availability helper in `FindAvailablePaymentDetail`;
- apply helper in `EnabledCardsController` active/available detail queries;
- apply helper in `MinAmountStatsService::activePaymentDetailsQuery`;
- apply helper in `HandleInertiaRequests` active detail counts;
- audit other active detail queries and classify them.

Acceptance:

- scheduled payment detail receives traffic only inside schedule intervals;
- unscheduled payment detail behaves as before;
- disabled payment detail never receives traffic even inside schedule;
- offline trader never receives traffic even inside schedule;
- enabled cards stats match schedule-aware availability;
- sidebar active counts become schedule-aware after existing cache refresh.

### Phase 6: Schedule Manager UI

Goals:

- provide full trader schedule management without leaving payment detail workflows.

Frontend tasks:

- build schedule manager modal;
- build schedule form with default days and per-day overrides;
- build interval add/remove UI;
- add copy action;
- add shared edit warning;
- show server-time helper text;
- show validation errors clearly;
- disable submit buttons while processing.

Acceptance:

- trader can manage schedule list from payment detail page;
- trader can create/edit/copy schedules;
- schedule form prevents obvious client-side invalid intervals before submit;
- backend validation errors are shown next to relevant fields;
- no delete action is present.

### Phase 7: Payment Detail Table Status

Goals:

- make schedule state visible and understandable in the payment detail table.

Backend tasks:

- include schedule status payload in payment detail index resource;
- include today's intervals, current interval, next interval, and server now ISO.

Frontend tasks:

- add schedule column near active status;
- render status label, schedule name, and interval;
- show `Без расписания` or dash when no schedule is attached;
- implement single page-level timer for relative labels;
- avoid per-row polling.

Acceptance:

- status labels are understandable;
- break between intervals displays as `Перерыв до HH:mm`;
- table clearly distinguishes no schedule, day off, starts later, working, break, and finished;
- displayed editable schedule times remain server-time values.

### Phase 8: Role Read Views

Goals:

- let Team Leaders and admins understand trader payment detail schedules without mutation access.

Tasks:

- include schedule name/status in pages where Team Leaders/admins already view trader payment details;
- avoid create/edit controls for schedules in those roles;
- ensure policy/controller restrictions prevent mutation.

Acceptance:

- Team Leader can see schedule state where they can see trader payment details;
- admin can see schedule state where they can see trader payment details;
- neither role can edit trader schedules in first version.

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

## Rollout Notes

This feature changes traffic eligibility, so implementation should be conservative:

- ship schedule filtering only after assignment UI and status UI are ready enough for traders to understand why traffic is not going to a detail;
- avoid hidden timezone conversions in editable inputs;
- prefer server-side authority for selection;
- keep frontend status display informational;
- keep old behavior for unscheduled details;
- leave existing 15-second active detail cache unchanged unless a later issue proves it harmful.

## Open Implementation Choices

The following choices can be made during implementation without changing product behavior:

- whether to persist effective expanded intervals only, or persist default rule plus overrides and derive effective intervals;
- whether adjacent intervals should be merged or preserved;
- whether the table status reloads at interval boundaries or only updates text locally until the next normal Inertia refresh;
- exact route names and component names, as long as they follow project conventions.

The recommended default is to persist effective expanded intervals because it makes traffic filtering simple and reliable.
