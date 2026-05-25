# Team Leader Shared Insurance — Phase 3 (Wallet Top-Up Behavior, Details)

> Source: Implementation session (Cursor agent), repository `p2p.cti`
> Collected: 2026-05-26
> Published: 2026-05-26

## Summary

Phase 3 implements wallet top-up routing for Team Leader shared insurance mode: traders under mode 2 credit deposits directly to `trust_balance`; Team Leader reserve top-ups credit only `reserve_balance` via a new `BalanceType::RESERVE`. Admin and Team Leader finance UI expose the shared reserve card and deposit flows. Order guards and split debit remain future phases (4–5).

## Backend

### BalanceType

`app/Enums/BalanceType.php` — new case `RESERVE = 'reserve'` for invoices/transactions affecting `wallets.reserve_balance` without mixing with `TEAMLEADER` income.

### Wallet handlers

- `app/Services/Wallet/GiveToBalanceHandler/GiveToReserve.php` — IN transactions only; adds to `reserve_balance`; records `BalanceType::RESERVE`.
- `app/Services/Wallet/TakeFromBalanceHandler/TakeFromReserve.php` — OUT from `reserve_balance` only (admin withdrawal path).
- `app/Services/Wallet/GiveToBalanceHandler/GiveToTrust.php` — if `wallet->user->usesTeamLeaderSharedReserve()`, entire amount to `trust_balance` (skip reserve-first split).
- `app/Services/Wallet/WalletService.php` — routes `RESERVE` in `giveToBalance` / `takeFromBalance`; `getTotalAvailableBalance(RESERVE)` returns `reserve_balance`.

`GiveToTeamleader` unchanged — Team Leader income still credits `teamleader_balance` only.

### TeamLeaderInsuranceService (wallet extensions)

- `validateAdminWalletDeposit()` — `balance_type=reserve` only for Team Leader in `team_leader_reserve` mode.
- `validateAdminWalletWithdraw()` — same for withdrawals.
- `teamLeaderUsesSharedReserve(?User $teamLeader): bool`
- `teamLeaderInsurancePropsForUser(User $user): ?array` — Inertia payload for TL (`role=team_leader`) or trader (`role=trader`, `uses_shared_reserve`).

### Controllers and routes

- `app/Http/Controllers/TeamLeader/DepositInvoiceController.php` — `POST leader/deposit/invoices` (`leader.deposit.invoices.store`); external deposit with `BalanceType::RESERVE`; 422 if TL not in shared mode.
- `app/Http/Controllers/WalletController.php` — passes `teamLeaderInsurance`; `resolveHistoryBalanceType()` returns `null` on `leader.finances.index` when shared mode (show all invoice/transaction balance types).
- `app/Http/Controllers/Admin/UserWalletController.php` — `walletSurfaces.reserve` for mode-2 Team Leader; history balance type unscoped; Super Admin filters include `reserve`; `teamLeaderInsurance` prop.

### Form requests

- `app/Http/Requests/Admin/User/Wallet/DepositRequest.php` — after-validator calls `validateAdminWalletDeposit`.
- `app/Http/Requests/Admin/User/Wallet/WithdrawRequest.php` — after-validator calls `validateAdminWalletWithdraw`.

### Invoice flow

`InvoiceService::deposit()` / `finishExternalDeposit()` unchanged entry points; `balance_type` on invoice drives handler selection. Trader external deposit still uses `BalanceType::TRUST` → `GiveToTrust` with shared-reserve branch.

## Frontend

- `resources/js/Pages/Wallet/Partials/TeamLeaderSharedReserveBalance.vue` — reserve card (balance, limits, threshold, trader count, info alert); admin deposit/withdraw; TL self deposit button.
- `resources/js/Modals/Wallet/LeaderReserveDepositModal.vue` — external payment via `leader.deposit.invoices.store`.
- `resources/js/Pages/Wallet/Partials/TrustBalance.vue` — info alert + hide reserve/limit when `teamLeaderInsurance.role === 'trader'` and shared reserve.
- `resources/js/Pages/Wallet/Index.vue` — `showTeamLeaderSharedReserveCard` from `walletSurfaces.reserve` or props.
- `resources/js/Modals/Wallet/DepositModal.vue` / `WithdrawalModal.vue` — `balance_type=reserve` titles and helpers.
- `resources/js/Pages/Wallet/Partials/OperationsHistory.vue` — label «Резерв» for `balance_type === 'reserve'`.
- `resources/js/store/modal.js` — `leaderReserveDeposit` modal.

## Not in Phase 3

- Order issuing guard when TL `reserve_balance` ≤ stop threshold.
- Mode-aware order debit / refund split (trader trust + TL reserve).
- Team Leader self-service withdrawal from reserve (admin only per spec).

## Manual verification (Phase 3)

- Trader under mode-2 TL: admin or external deposit → only `trust_balance` increases.
- TL mode 2: reserve deposit (admin or `leader.deposit.invoices.store`) → only `reserve_balance` increases.
- TL mode 2: `teamleader` deposit → only `teamleader_balance` increases.
- Admin cannot deposit `reserve` for TL in mode 1 or non-TL users.
