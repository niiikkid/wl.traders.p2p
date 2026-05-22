# Dispute Bank Statement Implementation Plan

> Sources: User conversation, 2026-05-22; repository exploration, 2026-05-22; Phase 1–4 implementation, 2026-05-22; Phase 5 role regression pass, 2026-05-22; Phase 6 formatting and verification, 2026-05-22
> Raw: [Dispute Bank Statement Requirements](../../raw/dispute-bank-statements/2026-05-22-dispute-bank-statement-requirements.md); [Phase 2 Validation and Service Implementation](../../raw/dispute-bank-statements/2026-05-22-phase-2-validation-service-implementation.md); [Phase 3 Inertia Rejection Modal Implementation](../../raw/dispute-bank-statements/2026-05-22-phase-3-inertia-rejection-modal-implementation.md); [Phase 4 Dispute Details UI Implementation](../../raw/dispute-bank-statements/2026-05-22-phase-4-dispute-details-ui-implementation.md); [Phase 5 Role Regression Pass](../../raw/dispute-bank-statements/2026-05-22-phase-5-role-regression-pass.md); [Phase 6 Formatting and Verification](../../raw/dispute-bank-statements/2026-05-22-phase-6-formatting-verification.md)
> Updated: 2026-05-22

## Overview

Dispute rejection in the application must require two pieces of evidence from any UI user who can reject an `Order` dispute: a rejection reason and a bank card statement file. The reason remains plain text in the existing `disputes.reason` column, while the file is stored separately as `bank_statement`. The feature affects only classic `Order`/`Dispute` flows and intentionally does not change API contracts or cascade dispute behavior.

## Product Scope

The feature applies whenever a dispute is rejected through the web interface by any role that already has the reject action:

- Trader;
- Super Admin / administrator UI;
- Support;
- Analyst.

The feature does not apply to:

- accepting disputes;
- cascade deal disputes;
- H2H/API/V2 payloads or responses;
- already rejected historical disputes unless they are reopened and rejected again.

The rejection form must always require a reason and a bank statement for currently pending disputes. Historical canceled disputes without a statement remain viewable and display `Нет файла` in the statement row.

## User-Facing Behavior

### Rejection Modal

The dispute rejection modal must replace the current free-text-only flow with a structured reason selector and file upload:

- the user chooses a reason preset from a dropdown;
- three fixed presets are available:
  - `Неверные реквизиты`;
  - `Фейковый чек`;
  - `Возврат платежа (карта заблокирована или достигнут лимит по карте)`;
- the dropdown also includes `Другая причина`;
- when a fixed preset is selected, the reason is fixed and should not be edited inline;
- when `Другая причина` is selected, a text input appears and the user enters a custom reason;
- the final reason is limited to 120 characters;
- the UI shows remaining characters while the user types a custom reason;
- the submit button is disabled while the form is processing and should not be submittable until both required fields are present.

The backend remains authoritative: it must validate both fields even if the frontend disables the submit button.

### Bank Statement Upload

The bank statement is a single file attached during rejection:

- accepted file types: JPG, JPEG, PNG, PDF;
- maximum size: 5 MB (`5120` KB);
- validation should reuse the payout receipt validation approach, especially `App\Rules\ReceiptFileRule`, because it handles the project’s reliable image/PDF checks and PDF fallback;
- the upload field label should clearly communicate that this is a bank/card statement for rejecting the dispute.

Although payout receipt uploads currently allow a larger size, this feature must keep the 5 MB limit from the requirements.

### Dispute Details Modal

For rejected disputes only, show a new row below `Квитанция`:

- label: `Выписка`;
- button text: `Выписка`;
- button color: DaisyUI `accent`;
- button opens the uploaded bank statement in a new tab;
- when no file exists, show `Нет файла`.

The row is visible only when `dispute.status === 'canceled'`. Accepted and pending disputes should not show a statement row because the statement is meaningful only for rejection.

The statement must be visible to the same users who can currently see the dispute receipt. In practice, reuse the same authorization surface as the receipt route, or define an equivalent gate that mirrors it.

## Existing Code Anchors

The implementation stays close to the current dispute flow:

**Implemented (Phase 1):**

- `app/Models/Dispute.php` — `bank_statement` in `$fillable` and PHPDoc.
- `app/Services/Dispute/DisputeService.php` — `storeBankStatement()`, `deleteBankStatement()`, `replaceBankStatement()`; directory constant `dispute-bank-statements`.
- `app/Http/Controllers/DisputeController.php::bankStatement()` — opens stored statement file.
- `routes/web.php` — `GET disputes/{dispute}/bank-statement` named `disputes.bank-statement` (same middleware group as `disputes.receipt`).
- `app/Providers/AppServiceProvider.php` — gate `access-to-dispute-bank-statement` mirrors `access-to-dispute-receipt`.
- `storage/dispute-bank-statements/.gitignore` — same ignore pattern as `storage/receipts`.

**Implemented (Phase 2):**

- `app/Http/Requests/Dispute/CancelRequest.php` — `reason` max 120, required `bank_statement` with `ReceiptFileRule` and 5 MB limit; localized attributes.
- `app/Contracts/DisputeServiceContract.php` — `cancel(..., UploadedFile $bankStatement)`.
- `app/Services/Dispute/DisputeService.php::cancel()` — `replaceBankStatement()` + persists `reason`, `bank_statement`, `status = canceled`; preserves `checkRejectedDisputesLimit()`.
- `app/Http/Controllers/DisputeController.php::cancel()`, `Support/DisputeController::cancel()`, `Analyst/DisputeController::cancel()` — pass validated reason and uploaded file.
- `app/Console/Commands/GenerateTestDataCommand.php` — `makeTestBankStatementFile()` for test-data dispute cancellations.

**Implemented (Phase 3):**

- `resources/js/Modals/CancelDisputeModal.vue` — reason preset dropdown, custom reason (max 120 + counter), `bank_statement` file upload, `form.patch` with `forceFormData: true`; used on Dispute/Order index pages for Trader, Support, Analyst (admin uses shared modal via Order/Dispute UI).

**Implemented (Phase 4):**

- `app/Http/Resources/DisputeResource.php` — `bank_statement`, `bank_statement_url` for panel UI.
- `app/Http/Resources/TableOrderResource.php` — same fields on embedded `dispute` for Order Index modals.
- `resources/js/Modals/DisputeModal.vue` — `showBankStatement()`, «Выписка» row for canceled disputes (`btn-accent`, «Нет файла» fallback).

## Data Model

**Implemented (Phase 1):** nullable column on `disputes`:

- `bank_statement`, nullable string, after `reason` or after `receipt`.

The column is nullable for backward compatibility with historical disputes that were canceled before the feature existed. New rejection requests enforce it through validation rather than a database `NOT NULL` constraint.

Update `Dispute` model metadata:

- add `bank_statement` to `$fillable`;
- add PHPDoc property `?string $bank_statement`.

Do not add a separate `reason_type` column. Presets are a frontend convenience only; the backend stores the final selected/custom reason as text in `reason`.

## File Storage

**Implemented (Phase 1):** store bank statement files separately from merchant receipts:

- directory: `storage/dispute-bank-statements` (created on first upload if missing);
- filename pattern: `bank_statement_<random32>.<extension>` via `DisputeService::storeBankStatement()`.

Prefer the storage style used by payout receipts for safer filenames:

- use a UUID or random string;
- preserve a sanitized extension from `getClientOriginalExtension()` / `extension()`;
- store only the relative filename/path in `disputes.bank_statement`.

**Implemented (Phase 2):** when a dispute is rejected (including after rollback), `replaceBankStatement()` runs inside `cancel()` together with `reason` and status update in one transaction.

## Authorization

**Implemented (Phase 1):** opening a bank statement is protected by the same access rules as dispute receipts:

- trader who owns the payment detail for the order;
- Super Admin;
- Support;
- Analyst.

- gate `access-to-dispute-bank-statement` in `AppServiceProvider` (duplicate of receipt gate logic).

The UI exposes `bank_statement_url` only through panel resources (`DisputeResource`, `TableOrderResource` embedded dispute); the file route authorizes server-side with the same gate as receipts.

## Backend Contract

### Request Validation

**Implemented (Phase 2):** `CancelRequest` validates:

- `reason`: `required`, `string`, `max:120`;
- `bank_statement`: `required`, `file`, `max:5120`, `ReceiptFileRule`;
- attributes: `причина отклонения`, `выписка по карте`.

**Implemented (Phase 3):** `CancelDisputeModal.vue` sends `reason` and `bank_statement` via `form.patch(..., { forceFormData: true })` to existing `disputes.cancel` / `support.disputes.cancel` / `analyst.disputes.cancel` routes. Client-only `reasonPreset` is not submitted to the backend.

### Service Contract

**Implemented (Phase 2):**

- `DisputeServiceContract::cancel(int $disputeID, string $reason, UploadedFile $bankStatement): bool`;
- `DisputeService::cancel()` stores the file via `replaceBankStatement()`, updates `status`, `reason`, and `bank_statement` in one transaction;
- preserves pending-status guard, order failure behavior, and `checkRejectedDisputesLimit()`.

All web/UI `cancel()` call sites updated (Trader, Support, Analyst). `GenerateTestDataCommand` passes a test PNG via `makeTestBankStatementFile()`. No API controllers call `cancel()`.

### File Route

**Implemented (Phase 1):** web route analogous to `disputes.receipt`:

- name: `disputes.bank-statement`;
- URL: `GET /disputes/{dispute}/bank-statement`;
- `DisputeController::bankStatement()` — gate + `response()->file()`; `404` when `bank_statement` is null or file missing on disk.

The route is UI-facing only. It is not in API route files.

### Resource

**Implemented (Phase 4):** `DisputeResource` and embedded dispute in `TableOrderResource` for web/Inertia:

- `bank_statement` => stored filename/path or `null`;
- `bank_statement_url` => `route('disputes.bank-statement', $id)` when a file exists, otherwise `null`.

Panel UI only. Do not add statement URLs to H2H/V2 order/dispute API resources.

## Frontend Design

### `CancelDisputeModal.vue`

**Implemented (Phase 3).** The form is multipart and includes:

- `reasonPreset`;
- `reason`;
- `bank_statement`.

Frontend logic:

- if a fixed preset is selected, assign `form.reason` to that preset string;
- if `Другая причина` is selected, clear `form.reason` and show the text input;
- show remaining characters for custom input: `120 - form.reason.length`;
- prevent custom input beyond 120 characters at UI level;
- clear validation errors when the user changes the reason or file;
- send the file as `bank_statement`;
- close all modals and refresh current route after success, matching the current behavior.

The fixed preset `Возврат платежа` should include a short explanatory phrase in the UI, for example:

- visible option: `Возврат платежа (карта заблокирована или достигнут лимит)`;
- stored reason can be exactly the same string, as long as it remains under 120 characters.

### `DisputeModal.vue`

**Implemented (Phase 4).**

- `showBankStatement()` opens `dispute.bank_statement_url` in a new tab (mirrors `showReceipt()`).
- Row «Выписка» directly below «Квитанция», only when `dispute.status === 'canceled'`.
- Button `btn btn-xs btn-outline btn-accent`, label text «Выписка»; otherwise muted «Нет файла».
- Pending and accepted disputes do not show the statement row.

The canceled reason block is unchanged; new rejections store reason text up to 120 characters.

## Backward Compatibility

Historical disputes can have:

- `status = canceled`;
- `reason` set;
- `bank_statement = null`.

The UI must render these safely. It should not attempt to require a statement retroactively.

If a historical accepted or canceled dispute is rolled back to pending and later rejected again, the new rejection request must require `bank_statement` and overwrite any previous value.

The nullable database column and UI `Нет файла` fallback are the compatibility boundary.

## Non-Goals

Do not include these in the first implementation:

- cascade dispute statement support;
- API request/response changes;
- multiple bank statements per rejection;
- separate reason type analytics;
- admin-configurable presets;
- notifications;
- virus scanning or OCR;
- changing merchant receipt behavior;
- changing dispute acceptance behavior.

## Implementation Phases

### Phase 1 — Backend Data and Storage Foundation

**Status: Done (2026-05-22).**

Deliverables:

- create migration adding nullable `disputes.bank_statement`;
- update `Dispute` model fillable/PHPDoc;
- add a storage helper in `DisputeService` or a small private method for statement files;
- add deletion/replacement logic for repeated rejection;
- add a controller method and route for opening bank statements;
- add/extend authorization gate mirroring receipt access.

Acceptance criteria:

- existing disputes still load with `bank_statement = null`;
- a stored statement can be opened only by users who can open the dispute receipt;
- no API routes are changed.

**Note:** the file route returns `404` until `bank_statement` is populated on the dispute record.

### Phase 2 — Validation and Service Contract

**Status: Done (2026-05-22).**

Deliverables:

- update `CancelRequest` with `reason max:120` and required `bank_statement`;
- reuse `ReceiptFileRule` and 5 MB max size;
- update `DisputeServiceContract`;
- update all web/UI controllers that call `services()->dispute()->cancel()`;
- ensure rejection stores `reason`, `bank_statement`, and `status = canceled`;
- preserve `checkRejectedDisputesLimit()`.

Acceptance criteria:

- rejecting without reason fails validation;
- rejecting without statement fails validation;
- unsupported file types fail with the existing receipt-file error style;
- files over 5 MB fail;
- repeated rejection after rollback overwrites the old statement.

### Phase 3 — Inertia Rejection Modal

**Status: Done (2026-05-22).**

Deliverables:

- replace free-text-only reason input with dropdown presets;
- show custom text input only for `Другая причина`;
- show remaining character count for custom reason;
- add file input for `bank_statement`;
- send multipart form data correctly;
- disable submit while processing and while required client-side fields are missing.

Acceptance criteria:

- fixed preset submits the preset text as `reason`;
- custom reason submits only when custom text is non-empty and <= 120 characters;
- selected file is sent as `bank_statement`;
- server validation errors render under the relevant fields.

### Phase 4 — Dispute Details UI

**Status: Done (2026-05-22).**

Deliverables:

- expose `bank_statement_url` in `DisputeResource`;
- add the `Выписка` row to `DisputeModal.vue` only for canceled disputes;
- use Accent button styling;
- show `Нет файла` for historical canceled disputes without a file.

Acceptance criteria:

- canceled dispute with statement opens the file in a new tab;
- canceled dispute without statement shows `Нет файла`;
- accepted/pending disputes do not show the statement row;
- receipt behavior remains unchanged.

### Phase 5 — Role Regression Pass

**Status: Done (2026-05-22).**

Deliverables:

- verify the rejection flow from Trader, Support, Analyst, and Admin UI surfaces that expose dispute rejection;
- verify routes used by each view still resolve correctly;
- confirm admin can keep using the shared endpoint if that is the current UI behavior.

Acceptance criteria:

- every UI role with reject action must provide reason + file;
- every role sees the statement in the same visibility scope as the receipt;
- no cascade UI was changed.

**Verified surfaces:**

| Role | Cancel route | UI pages with `CancelDisputeModal` |
|------|--------------|-----------------------------------|
| Trader | `disputes.cancel` | `Dispute/Index`, `Order/Index` |
| Super Admin | `disputes.cancel` (shared; no `admin.disputes.cancel`) | `Dispute/Index`, `Order/Index` via `Admin\DisputeController` / `Admin\OrderController` |
| Support | `support.disputes.cancel` | `Support/Dispute/Index`, `Support/Order/Index` |
| Analyst | `analyst.disputes.cancel` | `Analyst/Dispute/Index`, `Analyst/Order/Index` |

`CancelDisputeModal` selects the cancel route from `useViewStore` (analyst → support → default `disputes.cancel` for trader and admin). Bank statement download uses single route `disputes.bank-statement` with gate parity to `disputes.receipt`.

**Unchanged (out of scope):** cascade deals, API/H2H resources, Team Leader trader disputes (read-only).

### Phase 6 — Formatting and Focused Verification

**Status: Done (2026-05-22).**

Deliverables:

- run Pint on dirty PHP files;
- check frontend lints for edited Vue files;
- perform focused manual/browser verification if requested;
- do not run automated test suites unless explicitly requested by the user.

**Completed:**

- Pint on all dispute-bank-statement PHP files — pass, no changes;
- IDE lints on `CancelDisputeModal.vue`, `DisputeModal.vue` — clean;
- migration `add_bank_statement_to_disputes_table` confirmed **Ran**;
- `php artisan route:list --name=disputes` — cancel and `disputes.bank-statement` routes present for Trader/Support/Analyst;
- static code-path verification per checklist below (no defects found).

**Manual UI checklist** (operator, per role — not run in automated pass):

- reject pending dispute with preset + PNG under 5 MB;
- reject pending dispute with custom reason + PDF under 5 MB;
- attempt rejection with missing file;
- attempt rejection with missing reason;
- attempt file over 5 MB;
- rollback canceled dispute and reject again with a new file;
- open historical canceled dispute without statement.

## Implementation Status

| Phase | Status | Notes |
|-------|--------|-------|
| 1 — Backend data and storage | **Done** (2026-05-22) | Migration, model, storage helpers, file route, gate |
| 2 — Validation and service contract | **Done** (2026-05-22) | `CancelRequest`, `cancel()` + `replaceBankStatement()`, Trader/Support/Analyst controllers |
| 3 — Inertia rejection modal | **Done** (2026-05-22) | `CancelDisputeModal.vue` presets, file upload, `forceFormData` patch |
| 4 — Dispute details UI | **Done** (2026-05-22) | `DisputeResource`, `TableOrderResource`, `DisputeModal.vue` выписка row |
| 5 — Role regression pass | **Done** (2026-05-22) | All four roles: correct cancel routes, modals on all reject surfaces, receipt/statement gate parity |
| 6 — Formatting and verification | **Done** (2026-05-22) | Pint pass; Vue lints clean; migration/routes/static checks; manual UI checklist documented |

### Phase 1 artifacts (implemented)

**Migration** (2026-05-22):

- `database/migrations/2026_05_22_151044_add_bank_statement_to_disputes_table.php` — nullable `bank_statement` after `reason`

**Model** (`app/Models/Dispute.php`):

- `$fillable` includes `bank_statement`
- PHPDoc `@property string|null $bank_statement`

**Service** (`app/Services/Dispute/DisputeService.php`):

- `BANK_STATEMENT_DIRECTORY = 'dispute-bank-statements'`
- `storeBankStatement(UploadedFile): string`
- `deleteBankStatement(?string): void`
- `replaceBankStatement(?string, UploadedFile): string` — ready for Phase 2 `cancel()`
- `ensureBankStatementDirectoryExists()` — private

**Controller and route:**

- `DisputeController::bankStatement()`
- `routes/web.php` — `disputes.bank-statement` in `role:Trader|Support|Analyst|Super Admin` group (with `disputes.receipt`)

**Authorization:**

- `Gate::define('access-to-dispute-bank-statement', ...)` in `AppServiceProvider`

**Storage:**

- `storage/dispute-bank-statements/.gitignore` (`*` ignored, `!.gitignore`)

**Not changed in Phase 1:**

- `DisputeService::cancel()` signature and body
- `CancelRequest`, `DisputeResource`, Vue modals
- API routes and H2H dispute endpoints

### Phase 2 artifacts (implemented)

**Request** (`app/Http/Requests/Dispute/CancelRequest.php`):

- `reason` max 120; `bank_statement` required file max 5120 + `ReceiptFileRule`
- `attributes()` for Russian validation labels

**Contract and service:**

- `app/Contracts/DisputeServiceContract.php` — `cancel(..., UploadedFile $bankStatement)`
- `app/Services/Dispute/DisputeService.php::cancel()` — calls `replaceBankStatement()`, updates `bank_statement` column

**Controllers:**

- `DisputeController::cancel()`, `Support/DisputeController::cancel()`, `Analyst/DisputeController::cancel()` — `validated('reason')` + `file('bank_statement')`

**Test data:**

- `GenerateTestDataCommand::makeTestBankStatementFile()` — minimal PNG for generated canceled disputes

**Not changed in Phase 2:**

- `CancelDisputeModal.vue`, `DisputeModal.vue`, `DisputeResource`
- API routes and H2H dispute endpoints

### Phase 3 artifacts (implemented)

**Modal** (`resources/js/Modals/CancelDisputeModal.vue`):

- `useForm({ reasonPreset, reason, bank_statement })`
- Presets: `wrong_details`, `fake_receipt`, `payment_return`, `other` (`Другая причина`)
- Custom reason: TextInput + «Осталось символов: N»; max 120 enforced in UI (`watch` on `form.reason`)
- File: hidden input, `accept` JPG/JPEG/PNG/PDF, «Выбрать файл», selected filename display
- Submit: `form.patch` + `forceFormData: true`; route via `cancelDisputeRouteName` computed (`useViewStore`: analyst / support / default `disputes.cancel` for trader and Super Admin admin UI)
- `isSubmitDisabled` until preset, reason, and file present; reset on close and success

**Consumed on pages (unchanged wiring):**

- `Dispute/Index`, `Order/Index`, `Support/Dispute/Index`, `Support/Order/Index`, `Analyst/Dispute/Index`, `Analyst/Order/Index`

**Not changed in Phase 3:**

- `DisputeResource`, `DisputeModal.vue`
- API routes and H2H dispute endpoints

**End-to-end UI (Phases 1–6):** reject with reason + statement via `CancelDisputeModal` on all role surfaces; view statement in `DisputeModal` for canceled disputes. Phase 6 complete (Pint, lints, static/route checks; manual UI checklist in raw Phase 6 note).

### Phase 4 artifacts (implemented)

**Resources:**

- `app/Http/Resources/DisputeResource.php` — `bank_statement`, `bank_statement_url`
- `app/Http/Resources/TableOrderResource.php` — same fields on nested `dispute` for Order Index surfaces

**Modal** (`resources/js/Modals/DisputeModal.vue`):

- `showBankStatement()` — new tab via `bank_statement_url`
- «Выписка» row under «Квитанция» when `status === 'canceled'`
- `btn-accent` button or «Нет файла»

**Not changed in Phase 4:**

- `CancelDisputeModal.vue`
- API/H2H resources and routes
- Cascade dispute UI

### Phase 5 artifacts (verified)

**Route resolution** (`CancelDisputeModal.vue`):

- `cancelDisputeRouteName` — `computed` from `useViewStore`;
- analyst → `analyst.disputes.cancel`;
- support → `support.disputes.cancel`;
- trader and Super Admin (admin UI) → `disputes.cancel` (no `admin.disputes.cancel`; shared `DisputeController::cancel`).

**Controllers** (all use `CancelRequest` + `UploadedFile $bankStatement`):

- `DisputeController::cancel` — Trader + Super Admin;
- `Support\DisputeController::cancel`;
- `Analyst\DisputeController::cancel`.

**File access** (single route for all roles with receipt access):

- `GET disputes/{dispute}/bank-statement` — `DisputeController::bankStatement`;
- gates `access-to-dispute-receipt` and `access-to-dispute-bank-statement` — identical rules.

**UI mount points** (each page: `DisputeModal` + `CancelDisputeModal`):

- Trader: `Dispute/Index`, `Order/Index`;
- Admin: same components, routes `admin.disputes.index`, `admin.orders.index`;
- Support: `Support/Dispute/Index`, `Support/Order/Index`;
- Analyst: `Analyst/Dispute/Index`, `Analyst/Order/Index`.

**Explicitly unchanged:**

- `Admin\DisputeController` — index + store only (no cancel route);
- cascade deals, H2H/API resources, Team Leader `Leader/Trader/Disputes` (read-only).

### Phase 6 artifacts (completed)

**Formatting:**

- `vendor/bin/pint` on 11 PHP files listed in raw Phase 6 note — result `pass`, no diffs.

**Frontend:**

- `CancelDisputeModal.vue`, `DisputeModal.vue` — IDE linter clean.

**Infrastructure:**

- Migration `2026_05_22_151044_add_bank_statement_to_disputes_table` — Ran (batch 84).
- Routes verified: `disputes.cancel`, `support.disputes.cancel`, `analyst.disputes.cancel`, `disputes.bank-statement`.

**Not run (per plan):**

- PHPUnit / Pest suite;
- Browser regression across roles (checklist documented in raw Phase 6 note for manual execution).

**Feature status:** Phases 1–6 complete. Optional follow-ups: manual UI pass per role; automated tests only if explicitly requested.

## Edge Cases

- A user selects a fixed preset, then switches to `Другая причина`: clear fixed reason and require custom text.
- A user selects custom reason, types text, then switches to fixed preset: overwrite reason with preset.
- A file is selected, then replaced before submit: submit only the latest file.
- A dispute is no longer pending when submitted: preserve existing `Dispute must be pending.` behavior.
- A historical canceled dispute has no statement: render `Нет файла`, do not error.
- A stored statement file is missing from disk: the route should fail safely according to existing Laravel file response behavior; the UI still only knows whether the DB field exists.

## Open Implementation Notes

- Phase 3 uses `form.patch` with `forceFormData: true` for file upload; no `_method` POST workaround was needed.
- Phase 5 confirmed Super Admin rejects via `disputes.cancel`, not a prefixed admin route; `resolveViewMode()` on `admin.*` pages keeps `isAdminViewMode`, but cancel URL stays on the trader dispute group by design.
- Consider extracting shared file storage logic only if dispute receipts and bank statements start duplicating enough behavior; do not introduce a large abstraction for this single feature.
- If `DisputeResource` is later reused by public APIs, separate panel-only statement URL exposure from API resources to preserve the "API untouched" requirement.
