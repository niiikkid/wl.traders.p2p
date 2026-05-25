# Trader Balance Transfer Implementation Plan

> Sources: User conversation, 2026-05-22; repository exploration, 2026-05-22; implementation sessions, 2026-05-25–2026-05-26
> Raw: [Trader Balance Transfer Requirements](../../raw/trader-balance-transfers/2026-05-22-trader-balance-transfer-requirements.md); [Step 1 Trader Finances Located](../../raw/trader-balance-transfers/2026-05-25-step-1-trader-finances-located.md); [Steps 2–4 Transaction Types](../../raw/trader-balance-transfers/2026-05-25-step-2-4-transaction-types.md); [Step 5 Form Requests](../../raw/trader-balance-transfers/2026-05-26-step-5-form-requests.md); [Step 6 Transfer Service](../../raw/trader-balance-transfers/2026-05-26-step-6-trader-balance-transfer-service.md); [Steps 7–8 Controller and Routes](../../raw/trader-balance-transfers/2026-05-26-step-7-8-controller-routes.md); [Steps 9–15 Inertia Props and Vue UI](../../raw/trader-balance-transfers/2026-05-26-step-9-15-inertia-props-vue-ui.md)
> Updated: 2026-05-26

## Implementation Status

| Step | Status | Notes |
|------|--------|-------|
| 1. Locate trader finances page | **Done** | Anchors documented below; code helpers in `WalletController` |
| 2–4. `TransactionType` + localization | **Done** | `TRANSFER_TO_TRADER` / `TRANSFER_FROM_TRADER` in `TransactionType.php`; `lang/ru/transaction-type.php` |
| 5. Form requests | **Done** | `CheckRecipientRequest`, `StoreTransferRequest`, trait `AuthorizesTraderBalanceTransfer` |
| 6. Transfer service | **Done** | `TraderBalanceTransferService`, `TraderBalanceTransferException`; handlers on locked wallets |
| 7. Controller | **Done** | `TraderBalanceTransferController`: `recipient`, `store`; `TraderBalanceTransferException` → `422` + `{ message }` |
| 8. Routes `wallet.trader-transfer.*` | **Done** | `GET /wallet/trader-transfer/recipient`, `POST /wallet/trader-transfer` in trader group after `wallet.index` |
| 9. Inertia props | **Done** | `traderBalanceTransfer` on `wallet.index` via `traderBalanceTransferProps()` |
| 10. Vue modal | **Done** | `TraderBalanceTransferModal.vue` + `modal.js` `traderBalanceTransfer` |
| 11. Entry button | **Done** | `TrustBalance.vue` — «Перевести средства» when `available` |
| 12–14. Modal flow | **Done** | Recipient check, amount/2FA, `ConfirmModal` before POST |
| 15. Reload after success | **Done** | `router.reload` — `walletStats`, `invoices`, `transactions`, `traderBalanceTransfer` |
| 16. Pint | **Done** | `WalletController` formatted in implementation session |
| 17. Tests | Pending | Feature tests when explicitly requested |

Feature is **shipped** (API + UI, steps 1–15). Remaining: automated tests (step 17) and manual QA per **Testing Plan**.

## Trader Finances Page (Located)

Step 1 is complete. Use only this surface for the `Перевести средства` entry point and transfer routes.

| Item | Value |
|------|-------|
| URL | `GET /finances` |
| Route name | `wallet.index` |
| Middleware | `auth`, `banned`, `role:Trader\|Super Admin` |
| Route file | `routes/web.php` — trader group (~line 279) |
| Controller | `App\Http\Controllers\WalletController@index` |
| Balance type on page | `wallet.index` → `BalanceType::TRUST` (any role on this route) |
| Transfer UI scope | `WalletController::isTraderOwnFinancesPage()` — `routeIs('wallet.index')` + Trader role only |
| Inertia page | `resources/js/Pages/Wallet/Index.vue` |
| Trust balance card | `resources/js/Pages/Wallet/Partials/TrustBalance.vue` |
| Operation history | `resources/js/Pages/Wallet/Partials/OperationsHistory.vue` |
| Menu | `resources/js/Layouts/Partials/TraderMenu.vue` → `route('wallet.index')` |
| Nav title | `resources/js/Layouts/Partials/NavBar.vue` → «Финансы трейдера» |

Transfer routes are in the same trader middleware group, immediately after `wallet.index`:

| Method | URI | Route name | Controller |
|--------|-----|------------|------------|
| GET | `/wallet/trader-transfer/recipient` | `wallet.trader-transfer.recipient` | `TraderBalanceTransferController@recipient` |
| POST | `/wallet/trader-transfer` | `wallet.trader-transfer.store` | `TraderBalanceTransferController@store` |

### Code landed in step 1

- `WalletController::resolveBalanceType()` — `match` on route name (`wallet.index` → `BalanceType::TRUST`, etc.).
- `WalletController::isTraderOwnFinancesPage()` — private helper; `routeIs('wallet.index')` and user has Trader role.
- `WalletController::traderBalanceTransferProps()` — Inertia prop `traderBalanceTransfer` (step 9); `null` off `wallet.index`.
- `WalletController::canTraderBalanceTransfer()` — mirrors sender eligibility for `available` flag.
- PHPDoc on `isTraderOwnFinancesPage()` links to this wiki article.

### Not the trader's own finances page

| Item | Value |
|------|-------|
| URL | `GET /leader/traders/{trader}/finances` |
| Route name | `leader.traders.finances.index` |
| Controller | `App\Http\Controllers\TeamLeader\TraderFinanceController@index` |
| Inertia page | `resources/js/Pages/Leader/Trader/Finances.vue` |
| Purpose | Team Leader read-only view of a referral trader's wallet |

Provider liquidity uses `provider-liquidity.wallet.index` (`GET /provider-liquidity/wallet`), not `wallet.index`.

## Overview

Trader balance transfers let a trader move working funds to another trader under the same Team Leader. The source of funds is strictly the sender's `trust_balance`; reserve balance is never directly transferable. The recipient is found only by exact login (`users.email`) within the sender's Team Leader scope, and the transfer is written as two standard wallet transactions in one atomic operation.

## Product Scope

The first version is trader-to-trader only:

- the sender must be authenticated as a Trader;
- the sender must have `team_leader_id`;
- the recipient must be another Trader with the same `team_leader_id`;
- both users must be active: not archived and not blocked;
- the sender searches by exact login, not by ID and not by a list;
- no commissions, notifications, or separate transfer model are introduced;
- the standard wallet transaction history is the audit surface.

The feature belongs on the trader-facing `Финансы` page as a compact action, for example a `Перевести средства` button near the trust balance card or above operation history. Clicking it opens a modal where the trader searches the recipient, enters an amount, optionally enters 2FA, confirms, and submits the transfer.

## Existing Code Anchors

The implementation should reuse the current wallet and transaction foundation:

- `app/Models/User.php` has `team_leader_id`, `banned_at`, `archived_at`, `teamLeader()`, `referrals()`, and `wallet()`.
- `users.email` is the project login; `UserResource` exposes it as `login`.
- `app/Models/Wallet.php` stores `trust_balance` and `reserve_balance`.
- `app/Services/Wallet/WalletService.php` provides `takeFromBalance()` and `giveToBalance()`.
- `app/Services/Wallet/TakeFromBalanceHandler/TakeFromTrust.php` debits trust and creates an OUT transaction.
- `app/Services/Wallet/GiveToBalanceHandler/GiveToTrust.php` credits trust through existing reserve-first logic and creates an IN transaction.
- `app/Enums/BalanceType.php` already has `TRUST`.
- `app/Enums/TransactionType.php` controls transaction direction.
- `lang/ru/transaction-type.php` localizes transaction labels shown by `TransactionResource`.
- `resources/js/Pages/Wallet/Partials/OperationsHistory.vue` displays transaction history.
- `resources/js/Pages/Leader/Trader/Finances.vue` + `App\Http\Controllers\TeamLeader\TraderFinanceController` — Team Leader read-only finances for a trader (`leader.traders.finances.index`). Do not add transfer UI here.
- Trader's own finances: `resources/js/Pages/Wallet/Index.vue` + `WalletController@index` (`wallet.index`, `GET /finances`). Helpers: `resolveBalanceType()`, `isTraderOwnFinancesPage()`, `traderBalanceTransferProps()`. See **Trader Finances Page (Located)** and **Inertia Props (step 9)** / **Vue UI (steps 10–15)**.
- `resources/js/Modals/Wallet/TraderBalanceTransferModal.vue` — transfer modal (steps 10–15).
- `resources/js/utils/truncateTrustBalanceForTransfer.js` — truncate-down helper for «Перевести всё».
- `resources/js/store/modal.js` — `openTraderBalanceTransferModal()`, `traderBalanceTransfer` modal state.
- `resources/js/Components/Modals/ConfirmModal.vue` is the existing simple confirmation modal pattern.
- `app/Http/Controllers/Auth/Check2FACodeController.php` shows how current Google 2FA codes are verified.
- `app/Services/Wallet/TraderBalanceTransferService.php` — recipient resolve/preview and atomic transfer (step 6).
- `app/Exceptions/TraderBalanceTransferException.php` — domain errors mapped to JSON in controller (step 7).
- `app/Http/Controllers/Wallet/TraderBalanceTransferController.php` — `recipient`, `store` JSON endpoints (steps 7–8).

## Domain Definitions

### Working Balance

Working balance is `wallets.trust_balance`. It is the only balance that can be used as the transfer source.

### Reserve Balance

Reserve/insurance balance is `wallets.reserve_balance`. It cannot be selected or debited as the source balance.

Traders under [Team Leader Shared Insurance Mode](../team-leader-insurance-mode/team-leader-shared-insurance-mode-spec.md) (mode 2) do not use personal reserve for top-ups or order debit; recipient `GiveToTrust` still credits `trust_balance` directly for those traders. Team Leader shared `reserve_balance` is out of scope for trader-to-trader transfers.

### Recipient Credit Behavior

Do not bypass existing `GiveToTrust` behavior on the recipient side. Incoming transfer funds must be credited through the same trust-credit path used elsewhere:

- if recipient reserve is below the required reserve limit, the incoming amount fills reserve first;
- remaining funds, if any, are added to recipient `trust_balance`;
- if reserve is already full, the full amount is added to recipient `trust_balance`.

This preserves the project's current invariant: trust credits can satisfy reserve requirements before increasing working funds.

## Transaction Types

**Shipped (steps 2–4, 2026-05-25).** Enum cases in `app/Enums/TransactionType.php`:

| Case | Value | Direction |
|------|-------|-----------|
| `TRANSFER_TO_TRADER` | `transfer_to_trader` | OUT (sender debit) |
| `TRANSFER_FROM_TRADER` | `transfer_from_trader` | IN (recipient credit) |

Russian labels in `lang/ru/transaction-type.php`:

- `transfer_to_trader` => `Перевод трейдеру`;
- `transfer_from_trader` => `Перевод от трейдера`.

These labels are enough for the standard history table because `TransactionResource` returns `type_name` from `transaction-type.<type>`. `TraderBalanceTransferService` uses `TransactionType::TRANSFER_TO_TRADER` and `TransactionType::TRANSFER_FROM_TRADER` via `TakeFromTrust` / `GiveToTrust` handlers.

## Form Requests (step 5)

**Shipped (2026-05-26).** Namespace `App\Http\Requests\Wallet\TraderTransfer`:

| Class | Endpoint | Role |
|-------|-------------------|------|
| `CheckRecipientRequest` | `GET wallet.trader-transfer.recipient` | Validates `login`; `recipientLogin()` for service lookup |
| `StoreTransferRequest` | `POST wallet.trader-transfer.store` | Validates `recipient_login`, `amount`, conditional `one_time_password`; `amountMoney()` for `Money` |

Shared trait `Concerns\AuthorizesTraderBalanceTransfer` enforces sender eligibility in `authorize()` (Trader role, `team_leader_id`, not archived/blocked). Failed authorize → 403 before field validation.

`StoreTransferRequest` specifics:

- `amount`: string regex `^\d+(\.\d{1,2})?$`, then `after()` ensures `Money::fromPrecision` is `greaterThanZero()`;
- `one_time_password`: required when `google2fa_secret` is set; OTP checked in `after()` via `pragmarx.google2fa` (not `user_2fa_passed`);
- Russian validation messages match **Copy** section below.

`TraderBalanceTransferController` type-hints these requests (step 7); `TraderBalanceTransferService` receives validated `recipientLogin()` / `amountMoney()` and does not re-validate 2FA or amount format.

## Controller and Routes (steps 7–8)

**Shipped (2026-05-26).** `App\Http\Controllers\Wallet\TraderBalanceTransferController` with constructor-injected `TraderBalanceTransferService`.

| Action | Form Request | Service call | Success response |
|--------|--------------|--------------|------------------|
| `recipient` | `CheckRecipientRequest` | `resolveRecipient` + `recipientPreview` | `200` JSON `{ login, avatar_uuid, avatar_style }` |
| `store` | `StoreTransferRequest` | `transfer` | `200` JSON `{ message: "Средства переведены." }` |

`failTransfer(TraderBalanceTransferException)` returns `422` with `{ message }` for all service-level failures (`recipientNotAvailable`, `insufficientTrustBalance`, `transferUnavailable`).

Laravel validation failures (amount regex, 2FA) return standard `422` with `errors` (not handled in controller). Failed `authorize()` on Form Requests → `403`.

Frontend can call `route('wallet.trader-transfer.recipient')` and `route('wallet.trader-transfer.store')` (Ziggy updated).

## Inertia Props (step 9)

**Shipped (2026-05-26).** On `wallet.index` only (`isTraderOwnFinancesPage()`), `WalletController@index` passes `traderBalanceTransfer`:

| Key | Source | UI use |
|-----|--------|--------|
| `available` | `canTraderBalanceTransfer($user)` | Show «Перевести средства» on trust card |
| `trust_balance` | `$wallet->trust_balance->toPrecision()` | «Перевести всё» via `truncateTrustBalanceForTransfer()` |
| `has_2fa` | `$user->google2fa_secret !== null` | Show 2FA field in modal |

Off `wallet.index` (admin user wallet, `leader.traders.finances.index`, merchant/agent/provider finances) the prop is `null`.

Eligibility for `available` matches `AuthorizesTraderBalanceTransfer`: Trader role, `team_leader_id`, not archived/blocked.

## Vue UI (steps 10–15)

**Shipped (2026-05-26).**

| Piece | Location |
|-------|----------|
| Entry button | `TrustBalance.vue` — `btn` «Перевести средства» in join before withdraw/deposit; `v-if="traderBalanceTransfer?.available"` and not admin wallet view |
| Modal | `TraderBalanceTransferModal.vue` — mounted from `Wallet/Index.vue` |
| Modal store | `modal.js` — `traderBalanceTransfer`, `openTraderBalanceTransferModal()` |
| Truncate helper | `truncateTrustBalanceForTransfer.js` |

Flow:

1. Open modal from trust card.
2. Enter login → **Проверить** → `GET wallet.trader-transfer.recipient?login=...` → avatar + login preview; preview cleared on login change.
3. Amount (≤2 decimals, sanitized on input); **Перевести всё** fills truncated `trust_balance`.
4. 2FA field when `has_2fa`.
5. **Перевести** → `ConfirmModal` («Подтвердите перевод») → `POST wallet.trader-transfer.store`.
6. Success → close modal, `router.reload({ only: ['walletStats', 'invoices', 'transactions', 'traderBalanceTransfer'], preserveScroll: true })`.

Copy matches **Copy** under **UI Requirements**. Backend validation errors map to field errors; service `422` `message` maps to recipient/amount/2FA as appropriate.

## Trader Balance Transfer Service (step 6)

**Shipped (2026-05-26).** `App\Services\Wallet\TraderBalanceTransferService` (singleton in `AppServiceProvider`). Exceptions: `App\Exceptions\TraderBalanceTransferException`.

| Method | Role |
|--------|------|
| `resolveRecipient(User $sender, string $login): User` | Scoped lookup for recipient check endpoint |
| `recipientPreview(User $recipient): array` | `{ login, avatar_uuid, avatar_style }` — `login` is `email` |
| `transfer(User $sender, string $recipientLogin, Money $amount): void` | Atomic transfer |

Recipient scope (same as planned API): `User::role('Trader')`, same `team_leader_id`, exact `email`, not self, `banned_at` / `archived_at` null.

Inside `App\Utils\Transaction::run`:

1. Re-query recipient; `refresh()` sender and recipient; re-assert eligibility.
2. `lockWallets()` — sort wallet IDs, `lockForUpdate()` ascending by `id`.
3. `trust_balance >= amount` on locked sender wallet.
4. `TakeFromTrust` + `TRANSFER_TO_TRADER` (debit sender trust only when pre-check passes).
5. `GiveToTrust` + `TRANSFER_FROM_TRADER` (recipient reserve-first).

Does **not** call `WalletService::takeFromBalance` / `giveToBalance` — handlers run on already-locked rows to avoid nested locks.

| Exception factory | Message |
|-------------------|---------|
| `recipientNotAvailable()` | Трейдер не найден или недоступен для перевода. |
| `insufficientTrustBalance()` | Недостаточно средств на рабочем балансе. |
| `transferUnavailable()` | Перевод недоступен. |

`resolveWallet()` creates a wallet via `services()->wallet()->create()` when missing.

## Backend API

**Shipped (steps 7–8, 2026-05-26).** Thin `TraderBalanceTransferController` + Form Requests + `TraderBalanceTransferService`.

| Method | URI | Route name | Purpose |
|--------|-----|------------|---------|
| GET | `/wallet/trader-transfer/recipient?login=...` | `wallet.trader-transfer.recipient` | Check recipient |
| POST | `/wallet/trader-transfer` | `wallet.trader-transfer.store` | Execute transfer |

Routes live in the trader group (`auth`, `banned`, `role:Trader|Super Admin`) immediately after `wallet.index`.

### Recipient Check Endpoint

Request:

- `login`: required string, exact login to search.

Rules:

- authenticated user must be a Trader;
- authenticated user must have `team_leader_id`;
- authenticated user must not be archived or blocked;
- recipient query must be scoped to:
  - role Trader;
  - same `team_leader_id`;
  - `email === login`;
  - `id !== sender.id`;
  - `banned_at IS NULL`;
  - `archived_at IS NULL`.

Successful response should contain only safe confirmation data:

- `login`;
- avatar data already used in the UI for this user.

Do not return `id` to the frontend unless the current component architecture absolutely requires it. The transfer submit request should still identify the recipient by login, not ID.

Failure response should be generic:

```json
{
  "message": "Трейдер не найден или недоступен для перевода."
}
```

Use the same generic failure for missing Team Leader, wrong Team Leader, archived/blocked recipient, self-transfer, and role mismatch. This prevents user enumeration outside the allowed Team Leader scope.

### Transfer Submit Endpoint

Request:

- `recipient_login`: required string;
- `amount`: required numeric decimal string with maximum 2 digits after decimal point;
- `one_time_password`: required only when sender has `google2fa_secret`.

Server-side validation must enforce:

- sender is a Trader;
- sender has `team_leader_id`;
- sender is not archived or blocked;
- recipient is found by the same exact scoped query used by recipient check;
- recipient is not sender;
- recipient is not archived or blocked;
- amount is greater than zero;
- amount has no more than 2 decimal places;
- amount does not exceed sender `trust_balance`;
- 2FA code is valid for every transfer when sender has 2FA enabled.

Do not trust the frontend for precision, recipient availability, balance, role, Team Leader, or 2FA.

## Money And Precision Rules

USDT internally supports 8 decimals, but this feature accepts only 2 decimals from the UI and API contract.

Recommended server handling:

- treat the request amount as a string to avoid float drift;
- validate format with a regex such as `^\d+(\.\d{1,2})?$`;
- reject `0`, `0.0`, and `0.00`;
- convert the validated decimal string into the project's `Money` type using the established `Money` factory for precision strings;
- compare the resulting `Money` value against sender wallet `trust_balance`.

The `Перевести всё` button should calculate the visible amount from sender `trust_balance` by truncating down to 2 decimal places, not rounding:

- `10.99999999` -> `10.99`;
- `10.001` -> `10.00`;
- if truncation produces `0.00`, the button should be disabled or the submit should fail with the normal positive amount validation.

## Atomic Transfer Flow

**Shipped in `TraderBalanceTransferService::transfer()` (step 6).** Single `Transaction::run` with both wallets locked by ascending `id` before handler calls. 2FA stays in `StoreTransferRequest` (before the service is invoked).

1. Sender eligibility (Form Request `authorize()` + service `assertSenderEligible`).
2. 2FA when enabled (`StoreTransferRequest` only).
3. DB transaction: re-query recipient, `refresh()` users, re-check statuses.
4. Lock both wallets (`orderBy('id')` after sorting IDs).
5. `trust_balance >= amount` on locked sender wallet.
6. `TakeFromTrust` → `TRANSFER_TO_TRADER`.
7. `GiveToTrust` → `TRANSFER_FROM_TRADER`.

Rollback on any failure. No separate transfer model.

### Lock ordering (resolved)

Step 6 locks both wallets in deterministic ID order, then invokes `TakeFromTrust` / `GiveToTrust` on the locked models — not `WalletService::takeFromBalance` / `giveToBalance`.

## Important Implementation Detail: Recipient Reserve Logic

Recipient credit uses `(new GiveToTrust)->handle($recipientWallet, $amount, TransactionType::TRANSFER_FROM_TRADER)` on the locked wallet (same reserve-first behavior as `WalletService::giveToBalance` with `BalanceType::TRUST`).

The sender debit must still be limited to available `trust_balance` only. Do not rely on `TakeFromTrust` alone for this feature, because `TakeFromTrust` can draw from `reserve_balance` when trust becomes negative. The transfer service must prevent this by checking `amount <= trust_balance` before calling the debit path, and it should do that check under lock.

## 2FA Rules

2FA is required for every transfer when the sender has `google2fa_secret`.

Do not rely on `session('user_2fa_passed')`. That session flag is for login flow, not for money movement.

Suggested verification:

- if sender has no `google2fa_secret`, no 2FA field is required;
- if sender has `google2fa_secret`, require `one_time_password`;
- verify against `app('pragmarx.google2fa')->getCurrentOtp($sender->google2fa_secret)`;
- reject invalid code with a validation error on `one_time_password`.

Prefer extracting this into a small reusable service if no existing action already verifies transaction-level 2FA.

## UI Requirements

**Shipped (steps 10–15, 2026-05-26).** See **Vue UI (steps 10–15)** for file anchors.

### Entry Point

**Done.** `Перевести средства` button on the trader's own `Финансы` page (`TrustBalance.vue`). Visible only when:

- current user is a Trader;
- current user has `team_leader_id`;
- current user is not archived or blocked.

If the user has no Team Leader, hide the feature entirely or show a disabled state with a short explanation. The requirement says the functionality is unavailable, so hiding is acceptable if consistent with project UX.

### Modal Structure

The modal should guide the trader through a small flow:

1. Recipient login input.
2. `Проверить` button.
3. Recipient preview after successful check:
   - avatar;
   - login.
4. Amount input with 2 decimal precision.
5. `Перевести всё` button.
6. 2FA input if `has_2fa` is true.
7. Final confirmation action:
   - `Отмена`;
   - `Перевести`.

Use the existing `ConfirmModal` pattern for the final confirmation, or embed equivalent confirmation controls in the transfer modal if that is cleaner for passing amount, recipient, and 2FA state. The user must clearly confirm before the POST is sent.

### Frontend Validation

Frontend should help but not be authoritative:

- prevent more than 2 decimal places;
- prevent zero and negative values;
- disable submit until recipient has been successfully checked;
- invalidate recipient preview when the login input changes;
- disable submit while processing;
- show backend validation errors next to fields;
- after successful transfer, close modal and refresh wallet stats and operation history.

### Copy

Use simple Russian text:

- button: `Перевести средства`;
- modal title: `Перевод средств трейдеру`;
- recipient input label: `Логин получателя`;
- check button: `Проверить`;
- amount label: `Сумма`;
- max button: `Перевести всё`;
- 2FA label: `Код 2FA`;
- confirm button: `Перевести`;
- cancel button: `Отмена`;
- generic recipient error: `Трейдер не найден или недоступен для перевода.`;
- insufficient funds: `Недостаточно средств на рабочем балансе.`;
- invalid amount: `Введите сумму больше 0, максимум с двумя знаками после запятой.`;
- invalid 2FA: `Неверный код 2FA.`;
- success flash/toast if the project uses one: `Средства переведены.`

## Authorization And Security

Authorization should be enforced on backend regardless of UI visibility:

- only the authenticated trader can initiate a transfer from their own wallet;
- no request may provide sender ID;
- recipient ID from frontend should not be trusted or needed;
- recipient lookup must always be scoped by sender `team_leader_id`;
- recipient search must not expose traders outside scope;
- all failure modes related to recipient availability should use the same generic message;
- blocked or archived users cannot send or receive;
- amount and balance checks must run under lock;
- 2FA must be validated before committing balance changes.

## Error Matrix

| Case | Expected Result |
|------|-----------------|
| Sender has no Team Leader | Feature unavailable; backend rejects |
| Sender is blocked or archived | Backend rejects |
| Recipient login not found | Generic recipient error |
| Recipient under another Team Leader | Generic recipient error |
| Recipient is sender | Generic recipient error |
| Recipient blocked or archived | Generic recipient error |
| Recipient not Trader | Generic recipient error |
| Amount is zero or negative | Amount validation error |
| Amount has more than 2 decimals | Amount validation error |
| Amount exceeds sender `trust_balance` | Insufficient funds error |
| Sender attempts reserve transfer | Backend rejects because API supports only trust transfer |
| 2FA enabled and code missing | 2FA validation error |
| 2FA enabled and code invalid | 2FA validation error |
| Any DB operation fails mid-transfer | Roll back both wallet updates and both transaction inserts |

## Implementation Steps

1. ~~Locate the trader's own `Финансы` page and its controller/route.~~ **Done (2026-05-25)** — see **Trader Finances Page (Located)**.
2. ~~Add new `TransactionType` cases for outgoing and incoming transfer.~~ **Done (2026-05-25)**.
3. ~~Update `TransactionType::direction()` for both new cases.~~ **Done (2026-05-25)**.
4. ~~Add Russian localization for both transaction types.~~ **Done (2026-05-25)**.
5. ~~Create Form Requests (recipient check + transfer submit).~~ **Done (2026-05-26)** — see **Form Requests (step 5)**.
6. ~~Add `TraderBalanceTransferService` (recipient lookup, locks, atomic transfer).~~ **Done (2026-05-26)** — see **Trader Balance Transfer Service (step 6)**.
7. ~~Add a controller with two actions (check recipient, store transfer).~~ **Done (2026-05-26)** — see **Controller and Routes (steps 7–8)**.
8. ~~Add trader-authenticated routes `wallet.trader-transfer.*`.~~ **Done (2026-05-26)** — see **Trader Finances Page (Located)** route table.
9. ~~Expose enough props to the finance page (`traderBalanceTransfer`).~~ **Done (2026-05-26)** — see **Inertia Props (step 9)**.
10. ~~Create `TraderBalanceTransferModal.vue` + modal store entry.~~ **Done (2026-05-26)** — see **Vue UI (steps 10–15)**.
11. ~~Add «Перевести средства» on trust card.~~ **Done (2026-05-26)**.
12. ~~Recipient check in modal.~~ **Done (2026-05-26)**.
13. ~~Amount input + truncate-down «Перевести всё».~~ **Done (2026-05-26)** — `truncateTrustBalanceForTransfer.js`.
14. ~~ConfirmModal + optional 2FA.~~ **Done (2026-05-26)**.
15. ~~Reload wallet stats and history after success.~~ **Done (2026-05-26)**.
16. ~~Run Pint on changed PHP files.~~ **Done (2026-05-26)**.
17. When tests are requested, cover recipient lookup, forbidden cases, 2FA, precision, insufficient balance, reserve-first recipient credit, and atomic rollback behavior.

## Testing Plan

Automated tests should be added when requested. The minimum useful coverage is feature-level backend tests:

- trader without Team Leader cannot check recipient or transfer;
- sender cannot transfer to self;
- recipient under another Team Leader is not found;
- blocked recipient is not found;
- archived recipient is not found;
- blocked sender cannot transfer;
- archived sender cannot transfer;
- amount `0`, negative amount, and more than 2 decimals are rejected;
- amount greater than sender `trust_balance` is rejected;
- transfer creates one OUT transaction for sender and one IN transaction for recipient;
- transfer debits only sender `trust_balance`, never sender `reserve_balance`;
- recipient credit uses reserve-first behavior when reserve is below limit;
- invalid 2FA rejects transfer;
- valid 2FA allows transfer;
- if recipient credit fails, sender debit and transaction are rolled back.

Manual UI verification:

- button is visible only for eligible traders;
- modal recipient check shows avatar and login only for eligible recipient;
- changing login clears previous recipient preview;
- `Перевести всё` truncates, not rounds;
- submit is disabled during processing;
- operation history shows `Перевод трейдеру` and `Перевод от трейдера`.

## Open Implementation Notes

- ~~Wallet lock ordering for two-wallet transfer~~ — resolved in step 6 (`lockWallets` by ascending `id`).
- If avatar formatting is centralized in a resource, reuse that resource shape for the recipient preview (step 6 returns `avatar_uuid` / `avatar_style` from `User`).
- Success feedback uses Inertia partial reload (no new toast); API success message is `Средства переведены.` — align with flash/toast convention if product adds one later.
- Do not create a `TraderBalanceTransfer` model or table in the first version; the accepted requirement is to use existing wallet transaction records only.

## See Also

- [Team Leader Shared Insurance Mode Specification](../team-leader-insurance-mode/team-leader-shared-insurance-mode-spec.md) — shared reserve, split order debit, finance UI (Phases 1–6)
