# Phase 5 Role Regression Pass

> Source: Codebase audit (routes, controllers, Vue surfaces, gates)
> Collected: 2026-05-22
> Published: 2026-05-22

## Scope

Verify dispute rejection with `reason` + `bank_statement` and canceled-dispute «Выписка» visibility for Trader, Super Admin, Support, and Analyst UI surfaces. Confirm cascade/API paths unchanged.

## Route matrix

| Role | Cancel route | Middleware | Controller |
|------|--------------|------------|------------|
| Trader | `disputes.cancel` | Trader\|Super Admin | `DisputeController::cancel` |
| Super Admin (admin UI) | `disputes.cancel` (shared) | Trader\|Super Admin | `DisputeController::cancel` |
| Support | `support.disputes.cancel` | Support\|Super Admin | `Support\DisputeController::cancel` |
| Analyst | `analyst.disputes.cancel` | Analyst\|Super Admin | `Analyst\DisputeController::cancel` |

Bank statement file route (all roles with receipt access): `disputes.bank-statement` → `DisputeController::bankStatement`, gate `access-to-dispute-bank-statement` (mirrors receipt).

Admin panel has no `admin.disputes.cancel`; Super Admin uses shared `disputes.cancel` by design.

## UI surfaces (DisputeModal + CancelDisputeModal mounted)

| Role | Pages | Inertia route names |
|------|-------|---------------------|
| Trader | `Dispute/Index`, `Order/Index` | `disputes.index`, `orders.index` |
| Super Admin | Same components via admin controllers | `admin.disputes.index`, `admin.orders.index` |
| Support | `Support/Dispute/Index`, `Support/Order/Index` | `support.disputes.index`, `support.orders.index` |
| Analyst | `Analyst/Dispute/Index`, `Analyst/Order/Index` | `analyst.disputes.index`, `analyst.orders.index` |

`CancelDisputeModal` resolves cancel URL via `useViewStore`: analyst → `analyst.disputes.cancel`, support → `support.disputes.cancel`, trader and admin → `disputes.cancel`.

## Authorization parity (receipt vs bank statement)

Gate logic identical for `access-to-dispute-receipt` and `access-to-dispute-bank-statement`: payment-detail owner, Super Admin, Support, Analyst.

`DisputeResource` / `TableOrderResource` expose `bank_statement` and `bank_statement_url` for panel UI only.

## Out of scope (unchanged)

- Cascade deals UI (`Admin/CascadeDeals/Index.vue` local dispute flow)
- H2H/API `DisputeResource`
- Team Leader `Leader/Trader/Disputes` (read-only)
- `admin.disputes` routes: index + store only

## Regression result

All four roles use `CancelRequest` (reason max 120, required `bank_statement`, `ReceiptFileRule`, 5 MB) on their cancel endpoints. Canceled disputes show «Выписка» in `DisputeModal` when `bank_statement_url` is present; historical canceled without file show «Нет файла».

No code defects requiring route or controller changes. Phase 5 complete.
