# Team Leader Insurance Mode — Phase 6 (Frontend Clarity)

> Source: Phase 6 implementation session
> Collected: 2026-05-26
> Published: Unknown

## Scope

Polish wallet/finance UI for Team Leader shared insurance mode (mode 2):

1. History filters on `leader.finances.index` and admin `admin.users.wallet` for mode-2 Team Leaders: «Все операции», «Доход тимлидера», «Страховой резерв».
2. Balance type column in operations history when `walletHistoryShowsBalanceType` is true (invoices + transactions).
3. Admin wallet context for traders under shared reserve (team leader email, page-level alerts).
4. Team Leader reserve card: stop-threshold badge, remaining trader slots.
5. Teamleader income card subtitle clarifying separation from insurance reserve.

## Backend

- `TeamLeaderInsuranceService::sharedReserveHistoryBalanceFilterVariants()`
- `TeamLeaderInsuranceService::resolveSharedReserveHistoryBalanceType()`
- `teamLeaderInsurancePropsForUser()`: `reserve_at_stop_threshold`, `team_leader_email` for traders
- `WalletController` / `Admin\UserWalletController`: filters + `walletHistoryShowsBalanceType` prop

## Frontend

- `resources/js/utils/walletBalanceTypeLabel.js`
- `OperationsHistory.vue`, `Wallet/Index.vue`, `TrustBalance.vue`, `TeamLeaderSharedReserveBalance.vue`, `TeamleaderBalance.vue`
