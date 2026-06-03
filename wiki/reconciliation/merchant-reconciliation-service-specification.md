# Merchant Reconciliation Service Specification

> Sources: User conversation, 2026-06-03; SP24 Reconciliation API documentation, Unknown
> Raw: [SP24 Reconciliation API and Product Requirements](../../raw/reconciliation/2026-06-03-sp24-reconciliation-api-and-product-requirements.md)
> Updated: 2026-06-03

## Overview

The reconciliation service lets an admin choose a merchant, period, operation type, and configured provider integration, then compare our local payments/payouts with provider-side data. The first strategy is SP24 over HTTP API, but the domain must stay provider-agnostic: every adapter returns the same normalized operation records, while provider-specific auth, endpoints, pagination, status mapping, and optional capabilities remain inside the adapter.

## Product Goal

Admins need one place to answer a simple operational question: "For this merchant and this period, do our payments, payouts, commissions, statuses, and balances match the provider's records?"

The page should show:

- how many operations we have locally;
- how many operations the provider returned;
- how many matched exactly;
- which operations exist only locally;
- which operations exist only on the provider side;
- which operations have different amount, commission, merchant amount, currency, or status;
- total local amount, provider amount, and delta;
- total local commission, provider commission, and delta;
- optional local/provider balance comparison when the strategy supports balance.

The result must help an operator quickly identify which specific deals need manual investigation.

## Scope

### In Scope

- Admin-only reconciliation module.
- Merchant selection.
- Reconciliation integration settings per merchant/provider account.
- Secure storage of provider credentials.
- Manual reconciliation run by selected period, type, and status.
- Provider adapter abstraction.
- SP24 adapter as the first implementation.
- Normalized comparison engine for payments and payouts.
- Summary result and mismatch table.
- Reconciliation run history.
- Export of results to CSV/XLSX if the project already has an export pattern; otherwise CSV is enough for v1.

### Out of Scope for First Release

- Automatic scheduled reconciliation.
- Auto-fixing balances or operation statuses.
- Direct provider webhooks.
- Complex accounting ledger corrections.
- Multi-provider reconciliation in one run.
- Storing real provider tokens in documentation, source code, or logs.

## Core Concepts

### Reconciliation Integration

A reconciliation integration is a concrete external data source configured for one merchant and one provider account/channel.

Example SP24 integrations:

| Name | Merchant | Strategy | Type | Currency | External account id |
|------|----------|----------|------|----------|---------------------|
| SP24 Ctinc Pay UAH | SP24 | `sp24_api` | payments | UAH | `23` |
| SP24 Ctinc Payout UAH | SP24 | `sp24_api` | payouts | UAH | `26` |
| SP24 Ctinc Pay RUB | SP24 | `sp24_api` | payments | RUB | `34` |
| SP24 Ctinc Payout RUB | SP24 | `sp24_api` | payouts | RUB | `53` |

The admin should not have to choose a raw token during reconciliation. They choose a merchant and one of that merchant's enabled integrations.

### Strategy

A strategy is a provider-specific adapter implementation. It knows how to authenticate, request remote operations, parse responses, map statuses, and expose optional capabilities.

Strategies may differ:

- SP24 uses Bearer tokens.
- Another provider may use HMAC headers.
- Another provider may require a session token.
- Another provider may have no ping endpoint.
- Another provider may have no balance endpoint.
- Another provider may return CSV instead of JSON.
- Another provider may use different status names or date filters.

The rest of the application should not care. It should work with normalized records.

### Operation Type

Use a small domain enum:

- `payment` — incoming payment/deposit/pay-in/order.
- `payout` — outgoing payout/withdrawal.

Provider terms can be mapped into these two project terms.

### Normalized Provider Operation

Every strategy should return the same operation DTO shape:

| Field | Required | Notes |
|-------|----------|-------|
| `external_id` | yes | Provider-side operation id used as the main reconciliation key. SP24: `provider_payment_id` or `provider_withdrawal_id`. |
| `internal_id` | no | Our UUID if provider returns it. Useful for diagnostics, not always trusted as the primary key. |
| `type` | yes | `payment` or `payout`. |
| `currency` | yes | Currency from integration or provider response. |
| `amount` | yes | Gross operation amount. Use project `Money` conventions when persisted or compared with local models. |
| `merchant_amount` | no | Net merchant amount for payments when provider exposes it. |
| `commission` | no | Provider-side or reported commission. |
| `status` | yes | Normalized status. |
| `provider_status` | yes | Original provider status string for audit. |
| `created_at` | no | Provider-side creation date. |
| `completed_at` | no | Provider-side completion/finalization date. |
| `raw` | yes | Raw provider item with sensitive fields removed if needed. |

### Normalized Local Operation

Local operations should be converted into the same comparison shape before matching:

| Field | Required | Notes |
|-------|----------|-------|
| `external_id` | yes | Provider-side id saved in our local operation. This is the primary matching key. |
| `internal_id` | yes | Our local UUID/id. |
| `type` | yes | `payment` or `payout`. |
| `merchant_id` | yes | Selected merchant. |
| `currency` | yes | Operation currency. |
| `amount` | yes | Local amount. |
| `merchant_amount` | no | Local net merchant amount if applicable. |
| `commission` | no | Local commission. |
| `status` | yes | Local normalized status. |
| `local_status` | yes | Original project status for display. |
| `created_at` | yes | Local creation date. |
| `completed_at` | no | Local completion/finalization date. |
| `admin_url` | no | Link to operation details when available. |

## Matching Rules

### Primary Key

Match by provider-side external id:

- SP24 payments: `provider_payment_id`.
- SP24 payouts: `provider_withdrawal_id`.

The local side must identify where those ids are stored for each operation type. If a local operation has no provider external id, it should appear in a separate "missing external id" group because it cannot be safely matched.

### Secondary Diagnostics

If a provider returns `internal_id`, it can be used for diagnostics, but should not override the primary external id match. If `external_id` matches but `internal_id` differs, show it as a warning-level mismatch.

### Amounts

Compare amounts using exact minor-unit or `Money` value comparison, not floats.

For each matched operation:

- compare gross `amount`;
- compare `commission` if both sides have it;
- compare `merchant_amount` for payment operations if both sides have it;
- compute deltas.

If provider does not expose a field, mark the check as `not_applicable`, not as a mismatch.

### Statuses

Each strategy must map provider statuses to project-level normalized statuses:

- `success`
- `pending`
- `processing`
- `failed`
- `expired`
- `unknown`

Local statuses should also be mapped into this normalized set. The result should show both original statuses and normalized statuses.

SP24 uses:

- `success`
- `pending`
- `processing`
- `failed`
- `expired`
- `all` as filter value only, not as operation status.

### Dates

The reconciliation period must use the same semantic date on both sides:

- payments/orders: creation date for SP24 (`created_at`);
- payouts: finalization date for SP24 (`completed_at`).

For other strategies, the date basis must be explicit in the strategy metadata. The UI should show a short hint like "Payments are filtered by creation date; payouts by completion date" for the selected strategy.

### Balance

Balance reconciliation is optional. A strategy can expose `supportsBalance()`.

If supported:

- fetch provider balance at run time;
- calculate or fetch our local comparable balance for the same merchant/integration/currency;
- show provider balance, local balance, delta, and provider `as_of`.

If not supported:

- show "Balance check is unavailable for this integration".

## Mismatch Categories

Use explicit categories so the UI and exports are easy to filter:

| Category | Meaning | Severity |
|----------|---------|----------|
| `only_local` | We have the operation, provider does not. | high |
| `only_provider` | Provider has the operation, we do not. | high |
| `amount_mismatch` | Gross amounts differ. | high |
| `merchant_amount_mismatch` | Net merchant amounts differ. | medium |
| `commission_mismatch` | Commissions differ. | medium |
| `status_mismatch` | Normalized statuses differ. | medium/high depending on status pair. |
| `currency_mismatch` | Currencies differ. | high |
| `internal_id_mismatch` | External id matched but internal id differs. | warning |
| `missing_external_id` | Local operation cannot be matched safely. | high |
| `provider_parse_error` | Provider item could not be normalized. | high |

One operation can have multiple mismatch categories. The UI should show a primary category plus all detected differences.

## Admin UI

### Integration Settings Page

Admin should be able to manage reconciliation integrations.

Fields:

- merchant;
- display name;
- strategy key, for example `sp24_api`;
- operation type: payment, payout, or both if a future strategy safely supports both through one credential;
- currency;
- base URL;
- external account/provider id;
- enabled flag;
- credentials, stored securely;
- optional notes;
- last successful ping/check;
- last reconciliation run time.

Credentials should be entered through a secret field. Existing value should not be displayed back to the admin. The UI can show "Token is set" and allow replacement.

For SP24:

- auth type: Bearer token;
- base URL: `https://api.easy-pay-24.online`;
- account ids: `23`, `26`, `34`, `53`;
- ping endpoint is available and should be used for verification.

### Run Reconciliation Page

Filters:

- merchant;
- integration;
- operation type;
- date from;
- date to;
- status;
- optional "include balance check".

Validation:

- selected integration must belong to selected merchant;
- operation type must be supported by selected integration;
- date range must be valid;
- date range must not exceed strategy limit unless the strategy supports automatic chunking;
- SP24 direct request limit is 92 days, but the service may split larger runs into chunks in a later phase.

Actions:

- "Check connection" when strategy supports ping.
- "Run reconciliation".
- "Export result".
- "Open saved run".

### Result Page

Top summary cards:

- local records count;
- provider records count;
- matched count;
- mismatch count;
- only local count;
- only provider count;
- amount delta;
- commission delta;
- optional balance delta.

Tables:

- mismatches table by default;
- matched records table behind a filter or tab;
- provider fetch errors if any;
- chunks/pages fetched for audit.

Useful columns:

- mismatch categories;
- operation type;
- currency;
- external id;
- local internal id;
- provider internal id;
- local amount;
- provider amount;
- amount delta;
- local commission;
- provider commission;
- commission delta;
- local status;
- provider status;
- local date;
- provider date;
- link to local operation.

Filters:

- category;
- status pair;
- amount delta only;
- only local;
- only provider;
- search by external id/internal id.

## Backend Architecture

### Suggested Components

Use a small set of domain services instead of putting provider logic in controllers.

| Component | Responsibility |
|-----------|----------------|
| `ReconciliationIntegration` model | Stores merchant/integration metadata and encrypted credentials reference. |
| `ReconciliationRun` model | Stores one run request, status, selected period, summary, and errors. |
| `ReconciliationRunItem` model | Stores normalized comparison rows and mismatch details. |
| `ReconciliationStrategyInterface` | Provider adapter contract. |
| `Sp24ReconciliationStrategy` | SP24 implementation. |
| `ReconciliationStrategyRegistry` | Resolves strategy by key. |
| `ReconciliationProviderClient` or strategy-local client | Performs external requests. |
| `LocalReconciliationRepository` | Loads local payment/payout data for comparison. |
| `ReconciliationComparator` | Pure comparison logic between normalized local and provider operations. |
| `ReconciliationRunService` | Orchestrates fetch, normalize, compare, persist. |
| `ReconciliationCredentialService` | Handles encryption/decryption and masking of credentials. |

### Strategy Contract

The strategy interface should express capabilities instead of assuming all providers support the same API:

```php
interface ReconciliationStrategyInterface
{
    public function key(): string;

    public function displayName(): string;

    public function capabilities(): ReconciliationCapabilities;

    public function ping(ReconciliationIntegration $integration): ?ReconciliationPingResult;

    public function fetchOperations(ReconciliationFetchRequest $request): ReconciliationFetchResult;

    public function fetchBalance(ReconciliationIntegration $integration): ?ReconciliationBalanceResult;
}
```

`capabilities()` should include:

- supported operation types;
- supported statuses;
- whether ping is supported;
- whether balance is supported;
- maximum date range per request;
- maximum page size;
- whether the strategy can chunk periods internally;
- authentication type label;
- date basis per operation type.

### Fetch Result

`ReconciliationFetchResult` should include:

- normalized operations;
- provider meta totals if available;
- pages/chunks fetched;
- warnings;
- raw error details safe for admins;
- provider request id if available.

### Persistence

Minimum tables:

#### `reconciliation_integrations`

- `id`
- `merchant_id`
- `strategy`
- `name`
- `operation_type`
- `currency`
- `base_url`
- `external_account_id`
- `credentials` encrypted or stored as encrypted JSON
- `settings` JSON for strategy-specific non-secret config
- `is_enabled`
- `last_checked_at`
- `last_check_status`
- timestamps

#### `reconciliation_runs`

- `id`
- `reconciliation_integration_id`
- `merchant_id`
- `operation_type`
- `currency`
- `date_from`
- `date_to`
- `status_filter`
- `run_status`: pending, running, completed, failed, completed_with_warnings
- `summary` JSON
- `provider_balance` nullable
- `local_balance` nullable
- `balance_delta` nullable
- `started_at`
- `finished_at`
- `created_by`
- timestamps

#### `reconciliation_run_items`

- `id`
- `reconciliation_run_id`
- `match_status`: matched, mismatched, only_local, only_provider, skipped
- `categories` JSON
- `external_id`
- `local_internal_id`
- `provider_internal_id`
- `currency`
- `local_amount`
- `provider_amount`
- `amount_delta`
- `local_merchant_amount`
- `provider_merchant_amount`
- `merchant_amount_delta`
- `local_commission`
- `provider_commission`
- `commission_delta`
- `local_status`
- `provider_status`
- `local_raw` JSON nullable
- `provider_raw` JSON nullable
- `local_operation_url` nullable
- timestamps

For large providers, consider storing only mismatches by default and keeping full matched rows optional. For the first SP24 release, storing all normalized comparison rows is acceptable if volume is manageable.

## SP24 Strategy Details

### Configuration

Required settings:

- base URL;
- Bearer token;
- currency;
- operation type;
- external account id;
- provider display name.

Recommended setup:

- one integration per SP24 account/token/currency/type;
- verify each token using `/api/reconciliation/ping`;
- save ping result metadata in settings or last check fields.

### Operations

Payments:

- endpoint: `/api/reconciliation/orders`;
- provider id field: `provider_payment_id`;
- date basis: `created_at`;
- fields: `amount`, `merchant_amount`, `commission`, `status`, `created_at`.

Payouts:

- endpoint: `/api/reconciliation/payouts`;
- provider id field: `provider_withdrawal_id`;
- date basis: `completed_at`;
- fields: `amount`, `commission`, `status`, `created_at`, `completed_at`.

Balance:

- endpoint: `/api/reconciliation/balance`;
- supported.

Ping:

- endpoint: `/api/reconciliation/ping`;
- supported.

Limits:

- maximum period per direct request: 92 days;
- `per_page` maximum: 500;
- fetch all pages until `meta.last_page`.

### Status Mapping

Initial SP24 status mapping:

| Provider status | Normalized status |
|-----------------|-------------------|
| `success` | `success` |
| `pending` | `pending` |
| `processing` | `processing` |
| `failed` | `failed` |
| `expired` | `expired` |
| anything else | `unknown` |

## Security Requirements

- Do not commit provider tokens to code, wiki, seeders, or fixtures.
- Do not log Authorization headers.
- Do not log full request headers for provider calls.
- Mask credentials in admin UI.
- Store credentials encrypted.
- Restrict integration settings and run results to admin users.
- Include audit fields: who created/updated integration, who ran reconciliation.
- Avoid exposing raw provider payloads if they can contain sensitive data; store only required fields or sanitize raw JSON.

## Error Handling

Provider fetch errors should not leave the admin without context.

Show:

- which endpoint failed;
- HTTP status;
- safe error message;
- whether partial pages were fetched;
- whether the run was aborted or completed with warnings.

Run statuses:

- `pending`
- `running`
- `completed`
- `completed_with_warnings`
- `failed`

Examples:

- invalid token: run/check fails with provider auth error.
- invalid period: validation error before external request if known limit is exceeded.
- provider timeout: retry a small number of times, then mark failed or warning.
- one page parse error: mark run `completed_with_warnings` if other pages are usable and item-level errors are stored.

## Implementation Phases

### Phase 0. Discovery and Anchors

Goal: identify local data sources and exact provider id fields.

Tasks:

- locate payment/order model fields that store provider payment id;
- locate payout/withdrawal model fields that store provider withdrawal id;
- locate local amount, merchant amount, commission, status, currency fields;
- decide the local status normalization map;
- confirm merchant model and admin merchant selector pattern;
- confirm existing admin route/menu/page conventions;
- confirm existing secret/encrypted settings pattern in the project.

Exit criteria:

- local data mapping is documented;
- unresolved field questions are listed before schema work starts.

### Phase 1. Domain Model and Configuration

Goal: create persistent integration configuration and run history foundation.

Tasks:

- add `reconciliation_integrations`;
- add `reconciliation_runs`;
- add `reconciliation_run_items`;
- add enums/value objects for operation type, run status, match status, mismatch category;
- add encrypted credential handling;
- add admin CRUD or minimal create/edit/list for integrations;
- add SP24 integration records manually through admin UI or a controlled setup command after credentials are entered.

Exit criteria:

- admin can create disabled/enabled integration for a merchant;
- token is stored securely and masked on read;
- no reconciliation run yet required.

### Phase 2. Strategy Abstraction

Goal: make provider-specific code replaceable.

Tasks:

- define strategy interface and capabilities DTO;
- implement strategy registry;
- define normalized provider operation DTO;
- define fetch request/result DTOs;
- define ping/balance DTOs;
- implement base validation against strategy capabilities.

Exit criteria:

- application can resolve `sp24_api`;
- controller/service can ask selected strategy what it supports without knowing SP24 details.

### Phase 3. SP24 Adapter

Goal: fetch SP24 data safely.

Tasks:

- implement Bearer-token HTTP client;
- implement `ping`;
- implement paginated `orders` fetch;
- implement paginated `payouts` fetch;
- implement `balance`;
- normalize SP24 payloads;
- sanitize raw payloads;
- map statuses;
- handle 401, 422, timeouts, malformed JSON, and pagination inconsistencies.

Exit criteria:

- admin can check connection for SP24 integration;
- service can fetch all SP24 pages for a valid period;
- provider data is returned as normalized records.

### Phase 4. Local Data Adapter

Goal: produce normalized local operations for comparison.

Tasks:

- implement local repository for payments;
- implement local repository for payouts;
- apply merchant, currency, operation type, date range, and status filters;
- map local statuses to normalized statuses;
- produce local normalized operations;
- mark local rows without provider external id.

Exit criteria:

- service can return comparable local records for selected merchant/integration/period.

### Phase 5. Comparison Engine

Goal: compare normalized local and provider records independent of provider strategy.

Tasks:

- match by `external_id`;
- detect only-local and only-provider rows;
- compare amount, merchant amount, commission, status, currency, internal id;
- calculate deltas;
- calculate summary totals;
- classify mismatch categories;
- persist `reconciliation_runs` and `reconciliation_run_items`.

Exit criteria:

- a reconciliation run can be executed without admin UI polish;
- persisted result contains summary and item-level mismatches.

### Phase 6. Admin UI

Goal: let admins configure and run reconciliation from the panel.

Tasks:

- add admin menu item "Сверки";
- add integrations list/create/edit pages;
- add run form with merchant/integration/date/status filters;
- add result summary page;
- add mismatch table with filters and links to local operations;
- add connection check action for strategies that support ping;
- add balance panel when strategy supports balance.

Exit criteria:

- admin can configure SP24, run comparison, and inspect mismatches without developer help.

### Phase 7. Export and Operations

Goal: make results usable for support/accounting.

Tasks:

- add CSV export for run items;
- add export filters or export all mismatches;
- add admin-friendly error display;
- add retry action for failed runs if safe;
- add run history page;
- add audit fields to show who ran the reconciliation.

Exit criteria:

- operations team can share mismatch reports with merchant/provider managers.

### Phase 8. Hardening and Scale

Goal: make the feature safe for larger periods and providers.

Tasks:

- support chunking periods beyond provider limits;
- move long runs to queues if needed;
- add rate limiting/backoff per strategy;
- add idempotent run protection for duplicate clicks;
- add cleanup/retention policy for old run items;
- add optional scheduled runs after manual flow is stable.

Exit criteria:

- large reconciliations do not block web requests;
- old data growth is controlled;
- run state remains observable.

### Phase 9. Additional Provider Strategies

Goal: prove the abstraction with a second provider.

Tasks:

- implement another strategy with different auth/API shape;
- verify the UI does not need provider-specific changes;
- add strategy-specific settings schema;
- document provider-specific date/status/matching rules.

Exit criteria:

- second provider works through the same admin page and same result model.

## Testing Plan

Automated tests should be added when implementation starts.

Backend coverage:

- strategy capability validation;
- credential masking/encryption behavior;
- SP24 status mapping;
- SP24 pagination;
- SP24 error handling for 401/422/timeouts;
- local repository filtering;
- comparison engine categories and deltas;
- run persistence;
- admin authorization.

Frontend/manual coverage:

- integration create/edit with masked token;
- connection check success/failure;
- run form validation;
- result summary;
- mismatch filters;
- export.

## Open Questions

- Which exact local models represent the payment side for SP24: classic `Order`, `CascadeDeal`, or both?
- Which exact field stores `provider_payment_id` locally?
- Which exact local model/field stores `provider_withdrawal_id` for payouts?
- What is the canonical local commission field for payment and payout comparison?
- Should default reconciliation status be `success` only or `all`?
- Should SP24 account ids `23`, `26`, `34`, `53` be stored as informational external ids or used in local filtering?
- What local balance should be compared to SP24 `/balance`: merchant balance, provider account balance, or a calculated ledger balance?
- Should large runs store matched rows, mismatches only, or both?

## First Release Recommendation

Start with the smallest useful workflow:

1. Admin configures four SP24 integrations for SP24 merchant: UAH payments, UAH payouts, RUB payments, RUB payouts.
2. Admin verifies each credential with `ping`.
3. Admin runs a manual reconciliation for one integration and one period up to 92 days.
4. System fetches provider records, fetches local records, compares by provider external id, stores the run, and shows mismatches.
5. Admin exports mismatches to CSV.

This gives immediate operational value while keeping the architecture open for future providers with different APIs.
