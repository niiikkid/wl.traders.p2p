# Step 6: TraderBalanceTransferService

> Source: Implementation session (Cursor), repository `p2p.cti`
> Collected: 2026-05-26
> Published: 2026-05-26

## Summary

Shipped `TraderBalanceTransferService` and `TraderBalanceTransferException` for trader-to-trader `trust_balance` transfers within one Team Leader scope. Step 6 of the implementation plan; controller and routes remain step 7–8.

## Files added

- `app/Services/Wallet/TraderBalanceTransferService.php`
- `app/Exceptions/TraderBalanceTransferException.php`
- `AppServiceProvider`: singleton `TraderBalanceTransferService::class`

## Public API

| Method | Purpose |
|--------|---------|
| `resolveRecipient(User $sender, string $login): User` | Scoped recipient lookup; throws `recipientNotAvailable()` |
| `recipientPreview(User $recipient): array` | `{ login, avatar_uuid, avatar_style }` for UI (login = `email`) |
| `transfer(User $sender, string $recipientLogin, Money $amount): void` | Atomic debit/credit |

Resolve via `app(TraderBalanceTransferService::class)`.

## Recipient query scope

`User::role('Trader')` with:

- `team_leader_id` = sender's `team_leader_id`
- `email` = exact login
- `id` != sender
- `banned_at` and `archived_at` null

## Transfer flow (inside `Transaction::run`)

1. `assertSenderEligible` before transaction; re-check sender and recipient after `refresh()` inside transaction.
2. `lockWallets`: sort wallet IDs, `lockForUpdate()` in ascending `id` order.
3. `trust_balance >= amount` on locked sender wallet; else `insufficientTrustBalance()`.
4. `(new TakeFromTrust)->handle(..., TRANSFER_TO_TRADER)` — trust-only when balance sufficient (no reserve draw).
5. `(new GiveToTrust)->handle(..., TRANSFER_FROM_TRADER)` — reserve-first credit on recipient.

Does not call `WalletService::takeFromBalance` / `giveToBalance` (avoids nested per-wallet transactions); uses handlers directly on already-locked wallets.

2FA and amount format validation stay in `StoreTransferRequest` (step 5); service does not re-validate OTP.

## Exceptions (`TraderBalanceTransferException`)

| Factory | Message |
|---------|---------|
| `recipientNotAvailable()` | Трейдер не найден или недоступен для перевода. |
| `insufficientTrustBalance()` | Недостаточно средств на рабочем балансе. |
| `transferUnavailable()` | Перевод недоступен. (sender ineligible inside service) |

Controller (step 7) should map these to JSON/validation responses.

## Wallet creation

`resolveWallet()` creates wallet via `services()->wallet()->create($user)` if missing.

## Not in scope (step 6)

- Controller actions, routes `wallet.trader-transfer.*`
- Inertia props, Vue modal
- Automated tests (when explicitly requested)

## Next

Step 7: controller (`recipient`, `store`) type-hinting Form Requests and calling this service.
