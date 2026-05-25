# Phase 3: Wallet Top-Up Behavior

**Collected:** 2026-05-26
**Published:** Unknown
**Source:** Implementation session (Team Leader Shared Insurance mode)

## Summary

- `BalanceType::RESERVE` for Team Leader shared insurance reserve movements.
- `GiveToReserve` / `TakeFromReserve` handlers; wired in `WalletService`.
- `GiveToTrust` credits only `trust_balance` when trader `usesTeamLeaderSharedReserve()`.
- Admin deposit/withdraw validation for reserve balance via `TeamLeaderInsuranceService`.
- Team Leader external deposit route `leader.deposit.invoices.store` with `BalanceType::RESERVE`.
- Finance UI: shared reserve card, leader reserve deposit modal, trader notice on trust card.
