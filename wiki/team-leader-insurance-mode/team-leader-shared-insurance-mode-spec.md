# Team Leader Shared Insurance Mode Specification

> Sources: User conversation, 2026-05-22; repository exploration, 2026-05-22
> Raw: [Team Leader Insurance Mode Requirements](../../raw/team-leader-insurance-mode/2026-05-22-team-leader-insurance-mode-requirements.md)
> Updated: 2026-05-22

## Overview

The Team Leader feature needs a second operating mode where connected traders share the Team Leader's `reserve_balance` as their insurance reserve. Existing behavior remains the default mode: each trader maintains their own reserve. The new mode must be tightly scoped so it affects only traders connected to Team Leaders explicitly configured for shared insurance, while Team Leader income continues to use `teamleader_balance` and never mixes with reserve funds.

## Existing Code Anchors

The implementation should preserve current wallet and Team Leader architecture:

- `app/Models/User.php` already has `team_leader_id`, `reserve_balance_limit`, `teamLeader()`, and `referrals()`.
- `app/Models/Wallet.php` already stores `trust_balance`, `reserve_balance`, and `teamleader_balance`.
- `app/Services/Wallet/WalletService.php` routes balance operations through `takeFromBalance()` and `giveToBalance()`.
- `app/Services/Wallet/GiveToBalanceHandler/GiveToTrust.php` currently credits trader top-ups reserve-first, then trust.
- `app/Services/Wallet/TakeFromBalanceHandler/TakeFromTrust.php` currently debits trader trust first and reserve when trust is insufficient.
- `app/Services/Wallet/GiveToBalanceHandler/GiveToTeamleader.php` credits Team Leader income to `teamleader_balance`.
- `app/Services/Order/Features/OrderDetailAssigner.php` debits `trader_paid_for_order` from the trader wallet when an order receives details.
- `app/Services/Order/Features/OrderOperator.php` refunds and re-debits trader wallet balances when dispute amount changes.
- `app/Services/User/UserService.php` currently makes `team_leader_id` effectively permanent after a trader receives one.
- `app/Http/Requests/Admin/User/StoreRequest.php`, `app/Http/Requests/Admin/User/UpdateRequest.php`, and `resources/js/Modals/User/UserCreateModal.vue` / `UserEditModal.vue` are the admin user configuration surface.
- `app/Http/Controllers/WalletController.php` currently scopes Team Leader finance pages to `BalanceType::TEAMLEADER`; this must be extended carefully because mode 2 also needs Team Leader reserve visibility and top-up.
- `app/Http/Controllers/Admin/UserWalletController.php` is the likely admin wallet operation surface for separate reserve top-ups/adjustments.

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

### Eligibility Check

For a trader connected to a mode 2 Team Leader, order issuing must be blocked when Team Leader `reserve_balance` is equal to or below the configured minimum threshold.

Example:

- threshold is `1000`;
- Team Leader `reserve_balance` is `1000`;
- issuing new trades to connected traders is forbidden.

The requirement is threshold-state based. The source states to block when the balance has already reached the limit, not only when a new trade would reduce it below the limit.

### Debit Source

When an order is assigned to a trader connected to a mode 2 Team Leader:

1. debit trader `trust_balance` first;
2. if trader `trust_balance` is insufficient, debit the remaining amount from Team Leader `reserve_balance`;
3. do not debit trader `reserve_balance`;
4. do not debit Team Leader `teamleader_balance`.

This mirrors the existing trader trust-then-reserve idea, but replaces the reserve source with Team Leader `reserve_balance`.

### Refund And Amount Change Symmetry

Every code path that reverses or recalculates a trader debit must restore funds to the same logical sources that were used during debit. Important paths include:

- order assignment debit;
- dispute amount change refund and re-debit;
- failed order refund;
- reopened order handlers.

If a debit can be split between trader `trust_balance` and Team Leader `reserve_balance`, the system needs enough audit data to refund accurately. If existing transaction history is insufficient to infer the split reliably, introduce a small internal allocation record or transaction metadata rather than guessing from current balances.

### Transaction History

Use ordinary wallet transactions unless implementation discovers a technical need for a new transaction type. Transactions should still make the affected wallet clear:

- trader trust debit is recorded on trader wallet;
- Team Leader reserve debit is recorded on Team Leader wallet;
- refunds are recorded on the wallet receiving the refund.

## Wallet Top-Up Rules

### Trader In Mode 1 Or Without Mode 2 Team Leader

Keep current behavior:

- top-ups use existing reserve-first logic;
- funds fill trader `reserve_balance` until trader reserve target is met;
- remaining funds credit trader `trust_balance`.

### Trader Connected To Mode 2 Team Leader

Change only this case:

- top-up amount credits directly to trader `trust_balance`;
- no funds credit trader `reserve_balance`;
- trader reserve target is ignored.

### Team Leader In Mode 2

Add a reserve top-up path:

- top-up amount credits directly to Team Leader `reserve_balance`;
- no overflow to Team Leader `teamleader_balance`;
- Team Leader income continues to credit `teamleader_balance` via existing income logic.

## Data Model Plan

Add columns to `users` for Team Leader configuration. Suggested names:

- `team_leader_insurance_mode` string/enum, default `trader_reserve`;
- `team_leader_trader_limit` unsigned integer nullable;
- `team_leader_reserve_balance_limit` unsigned decimal or integer in the project's established money precision;
- `team_leader_reserve_stop_threshold` unsigned decimal or integer in the project's established money precision.

If the project standard is to store reserve limits as precision integers on `users.reserve_balance_limit`, align with that style. Avoid reusing trader `reserve_balance_limit` for Team Leader shared reserve configuration unless the codebase review confirms there is no ambiguity, because trader reserve and Team Leader shared reserve have different semantics.

Add casts/accessors on `User` following existing conventions.

Add an enum for operating mode, for example `TeamLeaderInsuranceMode`, to avoid stringly typed checks across services, requests, and resources.

## Service Design Plan

Introduce a small domain service or support class for Team Leader insurance decisions, for example `TeamLeaderInsuranceService`:

- `usesSharedReserve(User $trader): bool`;
- `canConnectTrader(User $teamLeader, User $trader): bool` or validation helpers;
- `connectedTraderCount(User $teamLeader): int`;
- `ensureCanChangeMode(User $teamLeader, TeamLeaderInsuranceMode $targetMode): void`;
- `canIssueOrdersForTrader(User $trader): bool`;
- `debitTraderForOrder(User $trader, Money $amount, Model $transactionable): void`;
- `refundTraderOrderDebit(...)`.

Keep controllers thin. Put validation in Form Requests and balance mutations in services.

## Validation Plan

### Team Leader Store/Update Requests

Add validation rules for Team Leader mode fields:

- mode is required for Team Leader and defaults to mode 1;
- trader limit is required and positive for mode 2;
- required reserve amount is required and non-negative for mode 2;
- stop threshold is required and non-negative for mode 2;
- stop threshold must not exceed required reserve amount;
- mode 2 settings are ignored or nulled for non-Team Leader roles.

Add after-validation checks:

- block mode change when connected traders exist;
- block mode 2 to mode 1 when Team Leader `reserve_balance` is non-zero.

### Trader Connection Validation

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

Expose wallet stats for Team Leader reserve where needed. Current Team Leader finance scoping is centered on `BalanceType::TEAMLEADER`; mode 2 needs reserve data alongside income balance without redefining `teamleader_balance`.

## Frontend Plan

### Admin User Create/Edit Modal

Add Team Leader mode settings only when role is `Team Leader`:

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

### Team Leader Finance Page

Show separate sections:

- Team Leader income balance (`teamleader_balance`);
- shared insurance reserve (`reserve_balance`) when mode 2 is enabled.

Add reserve top-up action for mode 2 only.

Show the no-self-service-withdrawal explanation.

### Trader Finance Page

When trader is connected to a mode 2 Team Leader:

- show shared reserve notice;
- hide or de-emphasize trader reserve balance as inactive;
- make top-up flow direct to working balance.

## Implementation Phases

### Phase 1: Data And Domain Flags

1. Add Team Leader insurance mode enum.
2. Add migration for Team Leader mode/config fields.
3. Default existing Team Leaders to mode 1.
4. Add casts and fillable fields on `User`.
5. Expose fields through resources needed by admin and finance pages.

### Phase 2: Admin Validation And Configuration

1. Extend Team Leader create/update DTOs and Form Requests.
2. Add mode change restrictions.
3. Add trader limit validation when assigning `team_leader_id`.
4. Add zero-balance validation before connecting a trader to a mode 2 Team Leader.
5. Hide/reject trader reserve settings for traders under mode 2.

### Phase 3: Wallet Top-Up Behavior

1. Add a direct-to-trust top-up path for traders under mode 2.
2. Add Team Leader reserve top-up path that credits only `reserve_balance`.
3. Ensure Team Leader income still credits only `teamleader_balance`.
4. Ensure Team Leader reserve top-up never overflows to `teamleader_balance`.
5. Add UI affordances for Team Leader reserve top-up.

### Phase 4: Order Issuing Guard

1. Locate payment detail availability filtering and order detail assignment entry points.
2. Add guard that blocks traders under mode 2 when Team Leader reserve is equal to or below stop threshold.
3. Ensure blocked traders and archived traders continue to be excluded by existing logic.
4. Provide clear internal error reason for no available payment detail when reserve threshold blocks issuance.

### Phase 5: Split Debit And Refund Symmetry

1. Replace direct trader-only debit in order assignment with mode-aware debit.
2. For mode 2, debit trader `trust_balance` first and Team Leader `reserve_balance` for the remainder.
3. Record enough information to refund/recalculate exactly.
4. Update amount-change, failed-order refund, and reopened-order flows to use the same allocation logic.
5. Verify Team Leader `teamleader_balance` is never touched by these paths.

### Phase 6: Frontend Clarity

1. Update admin create/edit user modal.
2. Update Team Leader finance page with reserve section and admin-withdrawal message.
3. Update trader finance page with shared reserve message.
4. Add visible counts: connected trader count, limit, remaining slots.
5. Keep mode 1 UI behavior unchanged.

### Phase 7: Verification

Suggested focused checks:

- existing Team Leaders default to mode 1;
- mode cannot change with connected traders;
- mode 2 to mode 1 fails when Team Leader reserve is non-zero;
- trader connection fails when limit is exhausted;
- trader connection fails when any trader balance is non-zero;
- trader under mode 2 top-up goes directly to `trust_balance`;
- Team Leader reserve top-up credits only `reserve_balance`;
- Team Leader income credits only `teamleader_balance`;
- order issue is blocked when Team Leader reserve is equal to threshold;
- order debit uses trader trust first and Team Leader reserve second;
- refunds restore the same sources that funded the debit.

## Open Implementation Risks

The largest technical risk is refund symmetry. Current order debit paths call wallet service directly with `BalanceType::TRUST`, relying on `TakeFromTrust` to fall through to trader reserve when trust is insufficient. Mode 2 changes the reserve source to another wallet, so debit allocation may need explicit metadata or a dedicated allocation record to reverse correctly.

The second risk is UI scoping for Team Leader finances. Existing Team Leader finance pages appear scoped to `BalanceType::TEAMLEADER`, while mode 2 needs to show and top up `reserve_balance` without confusing it with Team Leader income.

## See Also

- [Trader Balance Transfer Implementation Plan](../trader-balance-transfers/trader-balance-transfer-implementation-plan.md)
