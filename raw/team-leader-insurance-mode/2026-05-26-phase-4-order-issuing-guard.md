# Phase 4: Order Issuing Guard

> Source: Implementation session (Team Leader Shared Insurance mode)
> Collected: 2026-05-26
> Published: Unknown

## Summary

Phase 4 blocks order traffic for traders under Team Leader shared reserve (`team_leader_reserve`) when the Team Leader wallet `reserve_balance` is at or below `team_leader_reserve_stop_threshold`.

## TeamLeaderInsuranceService

New API on `app/Services/User/TeamLeaderInsuranceService.php`:

| Method | Role |
|--------|------|
| `ORDER_ISSUE_BLOCK_REASON_RESERVE_THRESHOLD` | Log/ops reason key |
| `canIssueOrdersForTrader(User $trader): bool` | PHP guard; false when shared reserve and TL reserve ≤ threshold |
| `constrainEligibleTradersForOrderIssuing(Builder $userQuery): void` | SQL filter on online-trader queries |
| `isTeamLeaderReserveAtOrBelowStopThreshold(User $teamLeader): bool` | Compares TL `wallet.reserve_balance` to `team_leader_reserve_stop_threshold` via `Money::lessOrEquals` |

Threshold rule: block when `reserve_balance <= stop_threshold`; allow when `reserve_balance > stop_threshold` (or trader not on shared reserve, or threshold null).

## FindAvailablePaymentDetail

`queryPaymentDetails()` — after `constrainEligibleTradersForMerchant`, before wallet balance filter:

```php
->tap(fn (Builder $query) => app(TeamLeaderInsuranceService::class)->constrainEligibleTradersForOrderIssuing($query))
```

SQL shape (inside constrain): trader passes if no `team_leader_id`, or TL not in shared mode, or TL `reserve_balance` (cast DECIMAL) > `team_leader_reserve_stop_threshold`.

When `get()` returns no payment detail, `logWhenBlockedByTeamLeaderReserveThreshold()` runs an `exists()` query for online traders blocked only by threshold and logs `Log::info` with `reason` constant, `merchant_id`, amount, currency. User-facing message unchanged («Подходящие платежные реквизиты не найдены»).

## OrderDetailAssigner

Race guard before `order->update`: load trader with `teamLeader.wallet`, call `canIssueOrdersForTrader`. On failure: `Log::warning` + `OrderException::teamLeaderReserveStopThresholdReached()`.

## OrderException

`teamLeaderReserveStopThresholdReached()` — «Выдача сделок остановлена: резерв Team Leader на пороге или ниже.»

## Out of scope (Phase 5)

- Split debit trust → TL reserve on assign
- Refund symmetry
- Wallet balance pre-check in `FindAvailablePaymentDetail` still uses trader `trust_balance` only (TL reserve not counted for eligibility yet)

## Files touched

- `app/Services/User/TeamLeaderInsuranceService.php`
- `app/Services/Order/Features/OrderDetailProvider/Classes/FindAvailablePaymentDetail.php`
- `app/Services/Order/Features/OrderDetailAssigner.php`
- `app/Exceptions/OrderException.php`
