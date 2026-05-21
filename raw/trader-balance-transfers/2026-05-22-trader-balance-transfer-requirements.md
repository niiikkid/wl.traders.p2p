# Trader Balance Transfer Requirements

> Source: User conversation in Cursor
> Collected: 2026-05-22
> Published: Unknown

Business feature: allow traders to transfer working balance between traders under the same Team Leader.

## Core Requirement

Implement the ability to transfer working balance between traders inside one Team Leader.

Only traders with a linked Team Leader can use this feature. If a trader does not have a Team Leader, the transfer feature is unavailable.

The transfer is allowed only between traders who belong to the same Team Leader.

The sender must not see the full list of traders under the Team Leader. The recipient is found manually by exact login search.

## Sender And Recipient Rules

- A trader can transfer balance to another trader.
- The sender must have `team_leader_id`.
- The recipient is determined only by trader login.
- ID is not used in the user-facing search.
- Login is the `users.email` field in this project.
- The login must match exactly.
- Search must be limited to traders with the same `team_leader_id` as the sender.
- The recipient must not be the sender.
- The recipient cannot be found if archived or blocked.
- Transfer is forbidden if either sender or recipient is archived or blocked.
- A blocked user is a user with `banned_at != null`.
- An archived user is a user with `archived_at != null`.

## Balance Rules

- Only working balance can be transferred.
- Working balance means `trust_balance`.
- Insurance/reserve balance cannot be transferred directly.
- Insurance/reserve balance means `reserve_balance`.
- There are no transfer commissions.
- The transfer amount must not exceed the sender's available working balance.
- Zero and negative amounts are forbidden.
- USDT supports 8 decimals internally, but this UI must allow only 2 decimals.
- The "Transfer all" button should use the current `trust_balance`, truncated down to 2 decimals, not rounded.
- Example: `10.99999999` becomes `10.99`, not `11.00`.

## Recipient Deposit Behavior

Do not bypass existing trust deposit behavior.

When funds are credited to the recipient, the existing `GiveToTrust` behavior applies:

- if the recipient reserve balance is below the required reserve limit, the incoming transfer first fills reserve;
- only the remaining amount goes to `trust_balance`;
- if reserve is already full, the whole incoming amount goes to `trust_balance`.

This means the transfer source is strictly sender `trust_balance`, while the recipient side follows normal trust-credit rules.

## Recipient Search

The UI must provide recipient verification before transfer.

The trader enters a login and checks it. If found and available, the UI may show:

- login;
- the avatar currently used for that user.

If the login is not found, belongs to another Team Leader, belongs to the sender, or belongs to an archived/blocked trader, the UI should show one generic error:

> Трейдер не найден или недоступен для перевода.

This avoids leaking information about traders outside the allowed scope.

## Transfer Operation

The actual transfer is two wallet operations:

1. debit sender wallet;
2. credit recipient wallet.

Both operations must be atomic. Either both wallet balances and both transactions are saved, or nothing is saved.

Use the existing wallet/transaction system. Do not create a separate transfer model.

New transaction types are needed:

- outgoing: `Перевод трейдеру`;
- incoming: `Перевод от трейдера`.

These transaction types must be localized and visible in standard operation history.

## Confirmation And 2FA

The transfer modal must use confirmation UX with Cancel / Transfer actions.

If the user has 2FA enabled (`google2fa_secret` exists), they must enter a valid 2FA code for every transfer. Passing 2FA during login is not enough for transfer confirmation.

## Visibility

No custom visibility layer is required beyond standard transaction history:

- admin already sees transactions by wallet through the existing functionality;
- trader sees their own operation history;
- Team Leader existing finance views should show the transactions where applicable.

No notifications are required.

## Forbidden Cases

The system must reject transfer when:

- sender has no Team Leader;
- sender is archived or blocked;
- recipient is not found by exact login inside the same Team Leader;
- recipient is the sender;
- recipient is archived or blocked;
- recipient is not a Trader;
- amount is zero or negative;
- amount has more than 2 decimal places in the transfer UI/API contract;
- amount exceeds sender `trust_balance`;
- a request tries to transfer insurance/reserve balance;
- 2FA is enabled and the code is missing or invalid.
