# Phase 5: Split Debit And Refund Symmetry

> Source: implementation session, 2026-05-26
> Collected: 2026-05-26
> Published: Unknown

## Summary

- `OrderTraderDebitService` debits trader trust first, then Team Leader `reserve_balance` for shared-reserve traders.
- Order stores `trader_trust_paid_for_order` and `team_leader_reserve_paid_for_order` for symmetric refunds.
- Wired into assign, amount change, failed refund, reopen debit; eligibility filter sums trust + TL reserve.
- `TakeFromTrust` no longer falls through to trader reserve for shared-reserve traders.
- `TakeFromReserve` validates insufficient funds.
