# Trader Balance Transfer Implementation Plan

> Sources: User conversation, 2026-05-22; repository exploration, 2026-05-22
> Raw: [Trader Balance Transfer Requirements](../../raw/trader-balance-transfers/2026-05-22-trader-balance-transfer-requirements.md)
> Updated: 2026-05-22

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
- `resources/js/Pages/Leader/Trader/Finances.vue` is the Team Leader view for trader finances; the trader's own finance page should be located and updated according to existing routes.
- `resources/js/Components/Modals/ConfirmModal.vue` is the existing simple confirmation modal pattern.
- `app/Http/Controllers/Auth/Check2FACodeController.php` shows how current Google 2FA codes are verified.

## Domain Definitions

### Working Balance

Working balance is `wallets.trust_balance`. It is the only balance that can be used as the transfer source.

### Reserve Balance

Reserve/insurance balance is `wallets.reserve_balance`. It cannot be selected or debited as the source balance.

### Recipient Credit Behavior

Do not bypass existing `GiveToTrust` behavior on the recipient side. Incoming transfer funds must be credited through the same trust-credit path used elsewhere:

- if recipient reserve is below the required reserve limit, the incoming amount fills reserve first;
- remaining funds, if any, are added to recipient `trust_balance`;
- if reserve is already full, the full amount is added to recipient `trust_balance`.

This preserves the project's current invariant: trust credits can satisfy reserve requirements before increasing working funds.

## Transaction Types

Add two new `TransactionType` enum cases:

- outgoing debit from sender: `transfer_to_trader`;
- incoming credit to recipient: `transfer_from_trader`.

The outgoing type must return `TransactionDirection::OUT`.

The incoming type must return `TransactionDirection::IN`.

Add Russian localization in `lang/ru/transaction-type.php`:

- `transfer_to_trader` => `Перевод трейдеру`;
- `transfer_from_trader` => `Перевод от трейдера`.

These labels are enough for the standard history table because `TransactionResource` returns `type_name` from `transaction-type.<type>`.

## Backend API

Use Form Request classes for validation and keep controllers thin. Suggested endpoints:

- `GET /wallet/trader-transfer/recipient?login=...` checks recipient availability.
- `POST /wallet/trader-transfer` performs the transfer.

Route names should match existing route naming conventions for the trader wallet/finance area, for example:

- `wallet.trader-transfer.recipient`;
- `wallet.trader-transfer.store`.

If the project has a more specific trader finance route group, place the routes there instead of introducing a parallel convention.

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

The transfer must be a single database transaction with row locks for both wallets.

Recommended service method:

```php
public function transfer(User $sender, string $recipientLogin, Money $amount, ?string $oneTimePassword): void
```

Flow:

1. Validate sender role, status, and Team Leader.
2. Validate 2FA when enabled.
3. Start a DB transaction.
4. Re-query and lock sender wallet with `lockForUpdate()`.
5. Re-query recipient by exact scoped login and lock recipient wallet with `lockForUpdate()`.
6. Re-check sender and recipient statuses inside the transaction.
7. Re-check sender `trust_balance >= amount` inside the transaction.
8. Debit sender `trust_balance` using `WalletService::takeFromBalance(..., TransactionType::TRANSFER_TO_TRADER, BalanceType::TRUST)`.
9. Credit recipient using `WalletService::giveToBalance(..., TransactionType::TRANSFER_FROM_TRADER, BalanceType::TRUST)`.
10. Commit.

The transaction must fail completely if either wallet update, transaction insert, recipient lookup, status check, or 2FA validation fails.

### Lock Ordering

To reduce deadlock risk, lock wallets in a deterministic order by wallet ID when manually locking both records. If reusing `WalletService::takeFromBalance()` and `giveToBalance()` causes separate internal locks, wrap the outer process in a transaction and consider either:

- ensuring the service method locks both wallets first in deterministic order, then updates balances directly following existing handler behavior; or
- adding a wallet-service method designed for atomic two-wallet transfers.

Preserve existing transaction creation semantics and do not create a separate transfer model.

## Important Implementation Detail: Recipient Reserve Logic

Because the recipient credit must keep current reserve-first behavior, the safest approach is to call the existing trust credit path for the recipient:

```php
services()->wallet()->giveToBalance(
    walletID: $recipientWallet->id,
    amount: $amount,
    transactionType: TransactionType::TRANSFER_FROM_TRADER,
    balanceType: BalanceType::TRUST,
);
```

This allows `GiveToTrust` to decide how much goes to `reserve_balance` and how much goes to `trust_balance`.

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

### Entry Point

Add a `Перевести средства` button on the trader's own `Финансы` page. The button should be visible only when:

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

1. Locate the trader's own `Финансы` page and its controller/route. Do not confuse it with `Leader/Trader/Finances.vue`, which is the Team Leader view of a trader.
2. Add new `TransactionType` cases for outgoing and incoming transfer.
3. Update `TransactionType::direction()` for both new cases.
4. Add Russian localization for both transaction types.
5. Create Form Requests:
   - recipient check request;
   - transfer submit request.
6. Add a focused service, for example `TraderBalanceTransferService`, responsible for recipient lookup, 2FA verification, amount checks, wallet locks, and atomic transfer.
7. Add a controller with two actions:
   - check recipient;
   - store transfer.
8. Add trader-authenticated routes in the existing wallet/finance route group.
9. Expose enough props to the finance page:
   - whether transfer is available;
   - current trust balance for `Перевести всё`;
   - whether the user has 2FA enabled.
10. Create a Vue modal component for transfer, following existing modal/store patterns.
11. Add the `Перевести средства` entry button to the finance page.
12. Implement recipient check in the modal with exact login.
13. Implement amount input with max 2 decimals and truncate-down max amount.
14. Implement final confirmation and optional 2FA input.
15. On success, refresh wallet stats and operation history.
16. Run Pint on changed PHP files.
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

- If the existing wallet service cannot safely lock both wallets in deterministic order, add a dedicated transfer method rather than composing two public methods blindly.
- If avatar formatting is centralized in a resource, reuse that resource shape for the recipient preview.
- If there is already a project-level flash/toast convention, use it instead of introducing a new notification mechanism.
- Do not create a `TraderBalanceTransfer` model or table in the first version; the accepted requirement is to use existing wallet transaction records only.
