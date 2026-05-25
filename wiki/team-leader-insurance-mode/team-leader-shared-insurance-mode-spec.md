# Team Leader Shared Insurance Mode Specification

> Sources: User conversation, 2026-05-22; repository exploration, 2026-05-22; implementation sessions, 2026-05-26
> Raw: [Team Leader Insurance Mode Requirements](../../raw/team-leader-insurance-mode/2026-05-22-team-leader-insurance-mode-requirements.md); [Phase 1 Data And Domain Flags](../../raw/team-leader-insurance-mode/2026-05-26-phase-1-data-and-domain-flags.md); [Phase 2 Admin Validation And Configuration](../../raw/team-leader-insurance-mode/2026-05-26-phase-2-admin-validation-and-configuration.md); [Phase 3 Wallet Top-Up Behavior](../../raw/team-leader-insurance-mode/2026-05-26-phase-3-wallet-top-up-behavior.md); [Phase 3 Details](../../raw/team-leader-insurance-mode/2026-05-26-phase-3-wallet-top-up-behavior-details.md); [Phase 4 Order Issuing Guard](../../raw/team-leader-insurance-mode/2026-05-26-phase-4-order-issuing-guard.md); [Phase 5 Split Debit And Refund Symmetry](../../raw/team-leader-insurance-mode/2026-05-26-phase-5-split-debit-and-refund-symmetry.md); [Phase 6 Frontend Clarity](../../raw/team-leader-insurance-mode/2026-05-26-phase-6-frontend-clarity.md)
> Updated: 2026-05-26

## Implementation Status

| Phase | Status | Notes |
|-------|--------|-------|
| 1. Data and domain flags | **Done** | Enum, migration, `User` helpers, `UserResource` fields |
| 2. Admin validation and configuration | **Done** | `TeamLeaderInsuranceService`, Form Requests, DTOs, `UserService`, admin create/edit modals |
| 3. Wallet top-up behavior | **Done** | `BalanceType::RESERVE`, `GiveToReserve`/`TakeFromReserve`, direct-to-trust in `GiveToTrust`, admin + TL deposit UI |
| 4. Order issuing guard | **Done** | `constrainEligibleTradersForOrderIssuing`, `canIssueOrdersForTrader`, `FindAvailablePaymentDetail`, `OrderDetailAssigner` race guard |
| 5. Split debit and refund symmetry | **Done** | `OrderTraderDebitService`, order allocation columns, all debit/refund paths |
| 6. Frontend clarity | **Done** | History filters + balance column; admin/trader alerts; reserve stop badge; income card copy |
| 7. Verification | Pending | Focused checks from spec |

Feature is **not shipped** end-to-end. Phases 1–6 cover configuration, wallet, orders, and finance UI; manual verification (7) remains.

## Overview

The Team Leader feature needs a second operating mode where connected traders share the Team Leader's `reserve_balance` as their insurance reserve. Existing behavior remains the default mode: each trader maintains their own reserve. The new mode must be tightly scoped so it affects only traders connected to Team Leaders explicitly configured for shared insurance, while Team Leader income continues to use `teamleader_balance` and never mixes with reserve funds.

## Existing Code Anchors

The implementation should preserve current wallet and Team Leader architecture:

- `app/Models/User.php` already has `team_leader_id`, `reserve_balance_limit`, `teamLeader()`, and `referrals()`.
- `app/Models/Wallet.php` already stores `trust_balance`, `reserve_balance`, and `teamleader_balance`.
- `app/Services/Wallet/WalletService.php` routes balance operations through `takeFromBalance()` and `giveToBalance()`.
- `app/Services/Wallet/GiveToBalanceHandler/GiveToTrust.php` credits reserve-first, then trust — **except** traders with `usesTeamLeaderSharedReserve()` (Phase 3: direct to `trust_balance`).
- `app/Services/Wallet/GiveToBalanceHandler/GiveToReserve.php` / `TakeFromReserve.php` — Team Leader shared reserve IN/OUT (Phase 3).
- `app/Enums/BalanceType.php` — includes `RESERVE` for reserve-only wallet movements (Phase 3).
- `app/Services/Wallet/TakeFromBalanceHandler/TakeFromTrust.php` debits trader trust first and trader reserve when trust is insufficient — **except** shared-reserve traders (no trader-reserve fallback; order debit uses `OrderTraderDebitService`).
- `app/Services/Order/OrderTraderDebitService.php` — mode-aware order debit/refund and available-balance helper (Phase 5).
- `app/Services/Wallet/GiveToBalanceHandler/GiveToTeamleader.php` credits Team Leader income to `teamleader_balance` only.
- `app/Services/Order/Features/OrderDetailAssigner.php` — **Phase 4:** `canIssueOrdersForTrader()` before `order->update`; **Phase 5:** `OrderTraderDebitService::debit()` after assign, persists allocation columns.
- `app/Services/Order/Features/OrderDetailProvider/Classes/FindAvailablePaymentDetail.php` — **Phase 4:** `constrainEligibleTradersForOrderIssuing()`; **Phase 5:** wallet filter allows trust + TL `reserve_balance` for shared-reserve traders.
- `app/Services/Order/Features/OrderOperator.php` — **Phase 5:** `OrderTraderDebitService::refund()` / `debit()` on dispute amount change.
- `app/Listeners/HandleOrderFinishedAsFailedListener.php` / `HandleOrderReopenedFormFailedListener.php` — **Phase 5:** symmetric refund / re-debit via `OrderTraderDebitService`.
- `app/Http/Controllers/OrderController.php`, `Support/OrderController.php`, `Analyst/OrderController.php` — **Phase 5:** `getAvailableDebitBalance()` before reopening failed orders.
- `app/Services/Order/ValueObjects/OrderDebitAllocation.php` — trust + TL reserve split value object returned from shared-mode debit.
- `app/Services/User/UserService.php` currently makes `team_leader_id` effectively permanent after a trader receives one.
- `app/Http/Requests/Admin/User/StoreRequest.php`, `app/Http/Requests/Admin/User/UpdateRequest.php`, and `resources/js/Modals/User/UserCreateModal.vue` / `UserEditModal.vue` are the admin user configuration surface.
- `app/Http/Controllers/WalletController.php` — `teamLeaderInsurance`, `walletHistoryShowsBalanceType`; **Phase 6:** shared-mode history filters on `leader.finances.index` via `resolvePaginatedHistoryBalanceType()`.
- `app/Http/Controllers/Admin/UserWalletController.php` — **Phase 3:** `walletSurfaces.reserve`, Super Admin `balanceTypes` filters; **Phase 6:** same shared-mode history filters for mode-2 Team Leader; `team_leader` eager-load for trader admin wallet.
- `resources/js/utils/walletBalanceTypeLabel.js` — balance type labels in history (Phase 6).
- `resources/js/Pages/Wallet/Partials/OperationsHistory.vue` — balance column when `walletHistoryShowsBalanceType` (Phase 6).
- `app/Http/Controllers/TeamLeader/DepositInvoiceController.php` — external reserve top-up (`leader.deposit.invoices.store`).

## Domain Model

### Operating Modes

Add an explicit Team Leader insurance operating mode:

- mode 1: trader-owned insurance reserve, current behavior, default for all existing and new Team Leaders;
- mode 2: Team Leader shared insurance reserve, where connected traders use Team Leader `reserve_balance`.

Use clear internal names such as:

- `trader_reserve` for mode 1;
- `team_leader_reserve` for mode 2.

Use clear Russian UI labels:

- `Вариант 1: страховой депозит у каждого трейдера`;
- `Вариант 2: общий страховой депозит Team Leader`.

### Team Leader Reserve Fields

Mode 2 requires Team Leader-level configuration:

- operating mode;
- trader account limit;
- required reserve amount;
- minimum reserve threshold for issuing trades.

The required reserve amount is analogous to a reserve target. When Team Leader tops up reserve, funds fill only `reserve_balance`; they never overflow into `teamleader_balance`.

The minimum reserve threshold blocks issuing trades when Team Leader `reserve_balance` is equal to or below the configured threshold.

### Balance Separation

The system must keep three concepts separate:

- trader `trust_balance`: trader working balance;
- Team Leader `reserve_balance`: shared insurance reserve used only by connected traders in mode 2;
- Team Leader `teamleader_balance`: Team Leader income balance.

Team Leader income always credits `teamleader_balance`, regardless of `reserve_balance` state. Connected trader reserve spending must never debit Team Leader `teamleader_balance`.

## Admin Functional Requirements

### Team Leader Create/Edit

Admin must be able to configure mode 2 when creating or editing a Team Leader:

- select operating mode;
- set trader account limit;
- set required Team Leader reserve amount;
- set minimum Team Leader reserve threshold.

The first mode is selected by default.

The UI should include explanatory text near the mode selector:

> Во втором варианте подключенные трейдеры используют общий страховой резерв Team Leader. Team Leader пополняет только резервный баланс. Доход Team Leader всегда зачисляется на Team Leader баланс и не используется для страховых списаний.

For reserve amount:

> Сумма, которую Team Leader должен внести на резервный баланс для работы подключенных трейдеров.

For stop threshold:

> Если резервный баланс Team Leader станет равен этой сумме или ниже, система перестанет выдавать сделки подключенным трейдерам.

### Mode Change Restrictions

Admin cannot change the Team Leader operating mode when the Team Leader has any connected traders.

Mode 1 to mode 2 is allowed only when connected trader count is zero.

Mode 2 to mode 1 is allowed only when:

- connected trader count is zero;
- Team Leader `reserve_balance` is zero.

Team Leader `teamleader_balance` does not block switching back to mode 1.

### Trader Connection Limit

When admin connects a trader to a Team Leader using mode 2:

- count all users with role `Trader` and `team_leader_id = TeamLeader.id`;
- include blocked and archived traders in the count;
- reject the connection when count is already at the configured limit;
- show a validation error similar to `Лимит трейдеров для этого Team Leader исчерпан.`;
- keep the existing invariant that a trader's Team Leader cannot be changed after assignment.

### Trader Balance Requirement Before Connection

A trader may be connected to a mode 2 Team Leader only when all trader wallet balances are zero. This includes at least:

- `trust_balance`;
- `reserve_balance`;
- `merchant_balance`;
- `provider_balance`;
- `commission_balance`;
- `teamleader_balance`;
- `agent_balance`.

If any balance is non-zero, admin must receive an error explaining that the trader balance must be cleared before connection.

### Trader Reserve Settings

For traders connected to mode 2 Team Leaders:

- hide reserve limit/settings in admin UI;
- reject or ignore backend changes to trader reserve settings;
- do not allow admin to configure trader reserve for this trader.

Backend enforcement is required even if the field is hidden in the UI.

## Team Leader Finance Requirements

Team Leader needs a way to top up only `reserve_balance` for mode 2. This top-up should be similar to the existing trader top-up flow, but with stricter destination behavior:

- incoming reserve top-up credits Team Leader `reserve_balance`;
- no portion of the top-up credits `teamleader_balance`;
- Team Leader cannot use self-service withdrawal from `reserve_balance`;
- reserve withdrawal is handled only by admin through the admin panel.

Team Leader UI must explicitly show:

- current shared insurance reserve balance;
- required reserve amount;
- stop threshold;
- connected trader limit and current connected trader count;
- text that reserve withdrawal is available only by request to admin.

Suggested Russian message:

> Резервный баланс используется как общий страховой депозит подключенных трейдеров. Вы можете пополнить только резервный баланс. Вывод резервного баланса выполняется администратором по вашему запросу.

## Trader Finance Requirements

When a trader is connected to a Team Leader using mode 2:

- trader sees that they work through Team Leader shared insurance reserve;
- trader top-ups credit directly to `trust_balance`;
- trader top-ups bypass trader `reserve_balance`;
- trader `reserve_balance` is not used in order debit logic;
- existing behavior remains unchanged for all other traders.

Suggested Russian message:

> Вы работаете через общий страховой резерв Team Leader. Пополнения зачисляются на основной баланс, резервный баланс трейдера не используется.

## Order Issuing And Debit Logic

### Eligibility Check — **Done (Phase 4)**

For a trader connected to a mode 2 Team Leader, order issuing must be blocked when Team Leader `reserve_balance` is equal to or below the configured minimum threshold.

Example:

- threshold is `1000`;
- Team Leader `reserve_balance` is `1000`;
- issuing new trades to connected traders is forbidden.

The requirement is threshold-state based. The source states to block when the balance has already reached the limit, not only when a new trade would reduce it below the limit.

### Debit Source — **Done (Phase 5)**

When an order is assigned to a trader connected to a mode 2 Team Leader, `OrderTraderDebitService::debit()`:

1. debits trader `trust_balance` first (partial amount only — no trader-reserve fallback);
2. debits the remainder from Team Leader `reserve_balance` via `BalanceType::RESERVE` / `TakeFromReserve`;
3. never debits trader `reserve_balance`;
4. never debits Team Leader `teamleader_balance`.

Mode 1 keeps a single `takeFromBalance(..., BalanceType::TRUST)` call; `TakeFromTrust` may still use trader reserve as today. Allocation columns stay `null`.

After debit, `OrderDetailAssigner` persists `trader_trust_paid_for_order` and `team_leader_reserve_paid_for_order` on the order (shared mode only).

### Refund And Amount Change Symmetry — **Done (Phase 5)**

`OrderTraderDebitService::refund()` restores funds to the same wallets that funded the debit:

| Path | Entry point |
|------|-------------|
| Order assign | `OrderDetailAssigner` → `debit()` |
| Dispute amount change | `OrderOperator::updateAmount` → `refund()` then `debit()` |
| Failed order cancel | `HandleOrderFinishedAsFailedListener` → `refund()` |
| Reopen from failed | `HandleOrderReopenedFormFailedListener` → `debit()` |

**Legacy orders** (`trader_trust_paid_for_order` is `null`): full `trader_paid_for_order` refunded to trader `TRUST` via `GiveToTrust` (reserve-first refill behavior unchanged).

**Shared-mode orders:** trust portion → trader `TRUST`; TL reserve portion → TL `RESERVE` via `GiveToReserve`.

### Transaction History — **Done (Phase 5)**

No new transaction types. Split debits create separate wallet transactions:

- trader OUT on `balance_type = trust`;
- Team Leader OUT on `balance_type = reserve` (when remainder > 0);
- refunds mirror with IN on the same balance types.

## Wallet Top-Up Rules

### Trader In Mode 1 Or Without Mode 2 Team Leader — **Unchanged**

- top-ups use existing reserve-first logic via `GiveToTrust`;
- funds fill trader `reserve_balance` until trader reserve target is met;
- remaining funds credit trader `trust_balance`.

### Trader Connected To Mode 2 Team Leader — **Done (Phase 3)**

- `GiveToTrust` early return: entire amount to `trust_balance`;
- trader `reserve_balance` not used; `reserve_balance_limit` ignored for top-up split;
- external trader deposit (`trader.deposit.invoices.store`) still uses `BalanceType::TRUST` → same handler branch.

### Team Leader In Mode 2 — **Done (Phase 3)**

- `BalanceType::RESERVE` + `GiveToReserve`: top-up credits only `reserve_balance`;
- no overflow to `teamleader_balance`;
- `BalanceType::TEAMLEADER` + `GiveToTeamleader`: income unchanged;
- admin deposit/withdraw via `admin.users.wallet.deposit|withdraw` with `balance_type=reserve`;
- Team Leader self-service: `POST leader/deposit/invoices` (external), no self-service reserve withdrawal.

## Data Model

### Shipped (Phase 1)

Migration `2026_05_25_211641_add_team_leader_insurance_mode_fields_to_users_table`:

| Column | Type | Default |
|--------|------|---------|
| `team_leader_insurance_mode` | string(32) | `trader_reserve` |
| `team_leader_trader_limit` | unsigned int, nullable | — |
| `team_leader_reserve_balance_limit` | unsigned int, nullable | — |
| `team_leader_reserve_stop_threshold` | unsigned int, nullable | — |

Enum `App\Enums\TeamLeaderInsuranceMode`: `TraderReserve`, `TeamLeaderReserve` (backed string values).

`User` cast: `team_leader_insurance_mode` → enum. Limits use `unsignedInteger` like trader `reserve_balance_limit` (precision integers on `users`, not wallet string amounts).

### User helpers (Phase 1)

- `usesTeamLeaderSharedReserve()` — trader under TL with `team_leader_reserve` mode
- `connectedTraderCount()` — referrals with role `Trader` (includes blocked/archived)
- `remainingTeamLeaderTraderSlots()` — nullable when no limit configured

### Order debit allocation (Phase 5)

Migration `2026_05_25_213948_add_order_debit_allocation_columns_to_orders_table` on `orders`:

| Column | Cast | Set when |
|--------|------|----------|
| `trader_trust_paid_for_order` | `BaseCurrencyMoneyCast` | Shared-mode debit; `null` on mode 1 / legacy |
| `team_leader_reserve_paid_for_order` | `BaseCurrencyMoneyCast` | Shared-mode debit; `null` on mode 1 / legacy |

`trader_paid_for_order` remains total trader debit (`traderDebit` from profit calculation). Allocation columns sum to this total on shared-mode orders.

### API exposure (Phase 1)

`UserResource` includes mode, labels, limits, thresholds, `connected_trader_count`, `remaining_team_leader_trader_slots`, `uses_team_leader_shared_reserve`; nested `team_leader` carries mode when relation loaded. Order allocation columns are internal (not exposed in `OrderResource` as of Phase 5).

### Shipped (Phase 2)

`app/Services/User/TeamLeaderInsuranceService.php` — admin validation and persist shaping:

- `teamLeaderConfigurationRules()` / `teamLeaderConfigurationAttributes()`
- `validateTeamLeaderConfiguration()` — mode change restrictions
- `validateTraderTeamLeaderAssignment()` — TL role, trader limit, zero balances (update only)
- `validateTraderReserveLimitChange()` — block trader reserve edits under shared reserve
- `resolveTeamLeaderConfigurationForPersist()` — null mode-2 fields when mode 1
- `shouldIgnoreTraderReserveLimit()` — `UserService` update guard

Wired from `StoreRequest` / `UpdateRequest`; DTOs and `UserService` persist Team Leader fields only for role `Team Leader`.

### Shipped (Phase 3)

**Wallet core**

| Piece | Path / symbol |
|-------|----------------|
| Balance enum | `App\Enums\BalanceType::RESERVE` |
| Credit reserve | `GiveToReserve` |
| Debit reserve | `TakeFromReserve` |
| Direct trust top-up | `GiveToTrust` + `User::usesTeamLeaderSharedReserve()` |
| Wallet routing | `WalletService::giveToBalance` / `takeFromBalance` / `getTotalAvailableBalance` |

**Insurance service**

- `validateAdminWalletDeposit()` / `validateAdminWalletWithdraw()`
- `teamLeaderUsesSharedReserve()` / `teamLeaderInsurancePropsForUser()`

**HTTP**

- `TeamLeader\DepositInvoiceController` → `leader.deposit.invoices.store`
- `WalletController` — `teamLeaderInsurance`, `walletHistoryShowsBalanceType`; shared-mode history via `sharedReserveHistoryBalanceFilterVariants()` (Phase 6)
- `Admin\UserWalletController` — `walletSurfaces['reserve']`, reserve in Super Admin filters
- `DepositRequest` / `WithdrawRequest` — after-validators

**Frontend**

- `TeamLeaderSharedReserveBalance.vue`, `LeaderReserveDepositModal.vue`
- `TrustBalance.vue` — shared-reserve notice, hide trader reserve row
- `Wallet/Index.vue`, `DepositModal.vue`, `WithdrawalModal.vue`, `OperationsHistory.vue`
- `store/modal.js` — `leaderReserveDeposit`

### Shipped (Phase 4)

**Order issuing guard** (`TeamLeaderInsuranceService`):

| Method | Role |
|--------|------|
| `ORDER_ISSUE_BLOCK_REASON_RESERVE_THRESHOLD` | Internal log reason key |
| `canIssueOrdersForTrader(User $trader)` | Returns false when trader uses shared reserve and TL `reserve_balance` ≤ `team_leader_reserve_stop_threshold` |
| `constrainEligibleTradersForOrderIssuing(Builder $userQuery)` | SQL filter: exclude shared-reserve traders whose TL reserve is at/below threshold |
| `isTeamLeaderReserveAtOrBelowStopThreshold(User $teamLeader)` | Money comparison helper |

**Traffic selection:** `FindAvailablePaymentDetail::queryPaymentDetails()` — `->tap(constrainEligibleTradersForOrderIssuing)` after merchant traffic categories, then wallet filter: `trust_balance >= required` **or** (shared-reserve trader and `trust + TL reserve >= required`). On empty result, `logWhenBlockedByTeamLeaderReserveThreshold()` logs when online blocked traders exist (user message unchanged).

**Assignment race guard:** `OrderDetailAssigner::assign()` — `canIssueOrdersForTrader` before `order->update`; `OrderException::teamLeaderReserveStopThresholdReached()` + `Log::warning`.

### Shipped (Phase 5)

**Order allocation columns** — migration `2026_05_25_213948_add_order_debit_allocation_columns_to_orders_table`:

| Column | Purpose |
|--------|---------|
| `trader_trust_paid_for_order` | Trust portion debited on trader wallet (shared mode) |
| `team_leader_reserve_paid_for_order` | Reserve portion debited on Team Leader wallet (shared mode) |

Null columns → legacy refund path (full amount to `BalanceType::TRUST` via `GiveToTrust` reserve-first refill).

**`OrderTraderDebitService`**

| Method | Role |
|--------|------|
| `getAvailableDebitBalance(User $trader)` | Trust + trader reserve (mode 1) or trust + TL reserve (mode 2) |
| `debit(...)` | Mode-aware debit; returns `OrderDebitAllocation` for shared mode |
| `refund(...)` | Restores trust and TL reserve from stored allocation |
| `hasAllocationSnapshot(Order $order)` | `trader_trust_paid_for_order !== null` |

**Wired paths:** `OrderDetailAssigner`, `OrderOperator::updateAmount`, `HandleOrderFinishedAsFailedListener`, `HandleOrderReopenedFormFailedListener`, `OrderController` / `Support` / `Analyst` accept-order balance check.

**Eligibility:** `FindAvailablePaymentDetail` wallet filter — trust alone OR trust + TL `reserve_balance` for shared-reserve traders.

**Guards:** `TakeFromTrust` throws on insufficient trust for shared-reserve traders (no trader-reserve fallback); `TakeFromReserve` throws on insufficient TL reserve.

### Shipped (Phase 6)

**Service** (`TeamLeaderInsuranceService`):

| Symbol | Role |
|--------|------|
| `sharedReserveHistoryBalanceFilterVariants()` | Filter options: `all`, `teamleader`, `reserve` (Russian labels) |
| `resolveSharedReserveHistoryBalanceType($filterKey)` | `null` for `all`, else `BalanceType` |
| `teamLeaderInsurancePropsForUser()` | Adds `reserve_at_stop_threshold` (TL); `team_leader_email` (trader under shared reserve) |

**Controllers**

| Controller | Behavior |
|------------|----------|
| `WalletController::index` | Filters on `leader.finances.index` when TL uses shared reserve; `walletHistoryShowsBalanceType=true` |
| `Admin\UserWalletController::index` | Same filters for admin view of mode-2 TL; `walletHistoryShowsBalanceType` also true for Super Admin full view; loads `teamLeader` relation for trader wallet |

**Inertia props**

| Prop | When |
|------|------|
| `walletHistoryShowsBalanceType` | TL shared mode on `leader.finances` / admin TL wallet, or Super Admin wallet |
| `teamLeaderInsurance.team_leader_email` | Admin wallet of trader under shared reserve |
| `teamLeaderInsurance.reserve_at_stop_threshold` | TL in mode 2 at/below stop threshold |

**Frontend**

| File | Change |
|------|--------|
| `walletBalanceTypeLabel.js` | Context-aware labels («Доход тимлидера», «Страховой резерв») |
| `OperationsHistory.vue` | Balance column on invoices + transactions when prop set |
| `Wallet/Index.vue` | Info alerts (admin TL/trader, TL own history hint) |
| `TrustBalance.vue` | Admin-specific shared-reserve copy with TL email |
| `TeamLeaderSharedReserveBalance.vue` | Stop badge, remaining slots |
| `TeamleaderBalance.vue` | Subtitle: income not used for insurance debits |

**Regression:** Mode 1 Team Leader and other roles unchanged — no `balanceTypes` history filter unless Super Admin or mode 2.

### Planned (Phase 7)

Manual verification checklist from spec.

## Validation Plan

### Team Leader Store/Update Requests — **Done (Phase 2)**

Validation rules for Team Leader mode fields:

- mode is required for Team Leader and defaults to mode 1;
- trader limit is required and positive for mode 2;
- required reserve amount is required and non-negative for mode 2;
- stop threshold is required and non-negative for mode 2;
- stop threshold must not exceed required reserve amount;
- mode 2 settings are ignored or nulled for non-Team Leader roles.

Add after-validation checks:

- block mode change when connected traders exist;
- block mode 2 to mode 1 when Team Leader `reserve_balance` is non-zero.

### Trader Connection Validation — **Done (Phase 2)**

When assigning `team_leader_id`:

- target user must still be a Team Leader;
- if Team Leader mode is 2, enforce trader limit;
- if Team Leader mode is 2, enforce all trader wallet balances are zero;
- keep existing behavior that `team_leader_id` cannot be changed once set.

## API And Resource Plan

Expose the required fields through `UserResource`:

- Team Leader insurance mode;
- trader limit;
- required reserve amount;
- stop threshold;
- connected trader count;
- remaining trader slots;
- whether current trader uses Team Leader shared reserve;
- shared reserve status messages for UI.

**Phase 3:** `teamLeaderInsurance` Inertia prop on `wallet.index`, `leader.finances.index`, and `admin.users.wallet`. Reserve amount on TL wallet via `walletStats.base.trustReserveAmount`.

**Phase 6:** `walletHistoryShowsBalanceType` on `Wallet/Index`. Mode-2 Team Leader history defaults to all balance types with optional filter (`all` / `teamleader` / `reserve`). `teamLeaderInsurance` for traders adds `team_leader_email`; for Team Leader adds `reserve_at_stop_threshold`.

## Frontend Plan

### Admin User Create/Edit Modal — **Done (Phase 2)**

Team Leader mode settings when role is `Team Leader` (`TeamLeaderInsuranceFields.vue`):

- mode selector with clear labels;
- trader limit input;
- required reserve amount input;
- stop threshold input;
- explanatory alert for mode 2.

Hide trader reserve settings when editing a trader connected to a mode 2 Team Leader.

Show backend validation errors for:

- exhausted trader limit;
- non-zero trader balances;
- forbidden mode change;
- non-zero Team Leader reserve when switching back to mode 1.

### Team Leader Finance Page — **Done (Phase 3 + 6)**

- separate card «Общий страховой резерв» (`TeamLeaderSharedReserveBalance.vue`) when mode 2;
- existing card «Баланс тимлидера» for `teamleader_balance` with income-vs-reserve subtitle (Phase 6);
- reserve top-up: admin modal (`balance_type=reserve`) or TL `LeaderReserveDepositModal` → external invoice;
- info alert: reserve withdrawal only via admin request;
- badge «Выдача сделок остановлена» when `reserve_at_stop_threshold` (Phase 6);
- connected traders count + remaining slots on reserve card (Phase 6);
- history filter «Все / Доход тимлидера / Страховой резерв» on `leader.finances` and admin TL wallet;
- `walletHistoryShowsBalanceType` + balance column on invoices/transactions;
- TL hint above history on own finances page.

### Trader Finance Page — **Done (Phase 3 + 6)**

**Done:**

- alert: «Вы работаете через общий страховой резерв Team Leader…»;
- hide reserve amount row and reserve limit badge on trust card;
- top-up still via `TraderDepositModal` → `GiveToTrust` direct-to-trust path.
- admin wallet: page alert + trust card copy with `team_leader_email`; `teamLeaderInsurance.team_leader_email` from backend.

## Implementation Phases

### Phase 1: Data And Domain Flags — **Done**

1. ~~Add Team Leader insurance mode enum.~~ → `app/Enums/TeamLeaderInsuranceMode.php`
2. ~~Add migration for Team Leader mode/config fields.~~ → `2026_05_25_211641_add_team_leader_insurance_mode_fields_to_users_table.php`
3. ~~Default existing Team Leaders to mode 1.~~ → column default `trader_reserve`
4. ~~Add casts and fillable fields on `User`.~~ → fillable + enum cast + helper methods
5. ~~Expose fields through resources needed by admin and finance pages.~~ → `UserResource` (finance-specific wallet props unchanged until Phase 3/6)

### Phase 2: Admin Validation And Configuration — **Done**

1. ~~Extend Team Leader create/update DTOs and Form Requests.~~ → `UserCreateDTO`, `UserUpdateDTO`, `StoreRequest`, `UpdateRequest`
2. ~~Add mode change restrictions.~~ → `TeamLeaderInsuranceService::validateTeamLeaderConfiguration()`
3. ~~Add trader limit validation when assigning `team_leader_id`.~~ → `validateTraderTeamLeaderAssignment()`
4. ~~Add zero-balance validation before connecting a trader to a mode 2 Team Leader.~~ → same (on update when `team_leader_id` first set)
5. ~~Hide/reject trader reserve settings for traders under mode 2.~~ → `validateTraderReserveLimitChange()` + `UserService` ignore + `UserEditModal` UI

**Files:** `app/Services/User/TeamLeaderInsuranceService.php`, `resources/js/Modals/User/Partials/TeamLeaderInsuranceFields.vue`, `UserCreateModal.vue`, `UserEditModal.vue`

### Phase 3: Wallet Top-Up Behavior — **Done**

1. ~~Add a direct-to-trust top-up path for traders under mode 2.~~ → `GiveToTrust` early return
2. ~~Add Team Leader reserve top-up path that credits only `reserve_balance`.~~ → `GiveToReserve`, admin deposit, `leader.deposit.invoices.store`
3. ~~Ensure Team Leader income still credits only `teamleader_balance`.~~ → unchanged `GiveToTeamleader`
4. ~~Ensure Team Leader reserve top-up never overflows to `teamleader_balance`.~~ → `GiveToReserve` isolated
5. ~~Add UI affordances for Team Leader reserve top-up.~~ → shared reserve card + modals

**Routes:** `leader.deposit.invoices.store` (Ziggy regenerated after route add).

**Not in Phase 3:** order guard, split debit, TL self-service reserve withdrawal.

### Phase 4: Order Issuing Guard — **Done**

1. ~~Locate payment detail availability filtering and order detail assignment entry points.~~ → `FindAvailablePaymentDetail`, `OrderDetailAssigner`
2. ~~Add guard that blocks traders under mode 2 when Team Leader reserve is equal to or below stop threshold.~~ → `constrainEligibleTradersForOrderIssuing`, `canIssueOrdersForTrader`
3. ~~Ensure blocked traders and archived traders continue to be excluded by existing logic.~~ → unchanged `banned_at` / `archived_at` filters on trader query
4. ~~Provide clear internal error reason for no available payment detail when reserve threshold blocks issuance.~~ → `ORDER_ISSUE_BLOCK_REASON_RESERVE_THRESHOLD` in `Log::info` / `Log::warning`; `OrderException::teamLeaderReserveStopThresholdReached()` on assign race

**Files:** `TeamLeaderInsuranceService.php`, `FindAvailablePaymentDetail.php`, `OrderDetailAssigner.php`, `OrderException.php`

### Phase 5: Split Debit And Refund Symmetry — **Done**

1. ~~Replace direct trader-only debit in order assignment with mode-aware debit.~~ → `OrderTraderDebitService`
2. ~~For mode 2, debit trader `trust_balance` first and Team Leader `reserve_balance` for the remainder.~~
3. ~~Record enough information to refund/recalculate exactly.~~ → `trader_trust_paid_for_order`, `team_leader_reserve_paid_for_order`
4. ~~Update amount-change, failed-order refund, and reopened-order flows to use the same allocation logic.~~
5. ~~Verify Team Leader `teamleader_balance` is never touched by these paths.~~ → only `BalanceType::RESERVE` on TL wallet

**Files:** `OrderTraderDebitService.php`, `OrderDebitAllocation.php`, migration `2026_05_25_213948_*`, `TakeFromTrust.php`, `TakeFromReserve.php`, `FindAvailablePaymentDetail.php`

### Phase 6: Frontend Clarity — **Done**

1. ~~Update admin create/edit user modal.~~ → Phase 2
2. ~~Update Team Leader finance page with reserve section and admin-withdrawal message.~~ → Phase 3 + 6
3. ~~Update trader finance page with shared reserve message.~~ → Phase 3 + 6
4. ~~Add visible counts: connected trader count, limit, remaining slots.~~ → Phase 3 + 6
5. ~~History UX: balance type filters and column for mode 2.~~ → Phase 6
6. ~~Mode 1 UI unchanged.~~ → filters/column only when `walletHistoryShowsBalanceType` or Super Admin full view

**Files:** `TeamLeaderInsuranceService.php`, `WalletController.php`, `Admin\UserWalletController.php`, `walletBalanceTypeLabel.js`, `OperationsHistory.vue`, `Wallet/Index.vue`, `TrustBalance.vue`, `TeamLeaderSharedReserveBalance.vue`, `TeamleaderBalance.vue`

### Phase 7: Verification

Suggested focused checks:

- existing Team Leaders default to mode 1;
- mode cannot change with connected traders;
- mode 2 to mode 1 fails when Team Leader reserve is non-zero;
- trader connection fails when limit is exhausted;
- trader connection fails when any trader balance is non-zero;
- ~~trader under mode 2 top-up goes directly to `trust_balance`;~~ → Phase 3
- ~~Team Leader reserve top-up credits only `reserve_balance`;~~ → Phase 3
- ~~Team Leader income credits only `teamleader_balance`;~~ → unchanged `GiveToTeamleader`
- ~~order issue is blocked when Team Leader reserve is equal to threshold;~~ → Phase 4
- ~~order debit uses trader trust first and Team Leader reserve second;~~ → Phase 5 (`OrderTraderDebitService`)
- ~~refunds restore the same sources that funded the debit;~~ → Phase 5 (allocation columns + `refund()`)
- reopen failed order respects combined trust + TL reserve balance → Phase 5 (`getAvailableDebitBalance`)

## Open Implementation Risks

**Refund symmetry (Phase 5 mitigated):** allocation columns + `OrderTraderDebitService` cover assign, amount change, cancel, and reopen paths. Residual risk: orders debited before migration have no snapshot and use legacy single-wallet refund.

**Out of scope for Phase 5:** `FundsHolderService` still uses generic `takeFromBalance` / `giveToBalance` with caller-selected balance types — not wired through `OrderTraderDebitService`.

**UI scoping (Phase 3 + 6 mitigated):** separate cards for income vs shared reserve; history filterable by balance type on `leader.finances` and admin TL wallet. Remaining risk: operators must choose correct `balance_type` on admin deposit (teamleader vs reserve).

## See Also

- [Trader Balance Transfer Implementation Plan](../trader-balance-transfers/trader-balance-transfer-implementation-plan.md)
