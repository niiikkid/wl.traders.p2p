# Trader Balance Transfer — Steps 9–15 (Inertia Props and Vue UI)

> Source: Implementation session (Cursor), repository state after merge
> Collected: 2026-05-26
> Published: 2026-05-26

## Summary

Shipped Inertia props on the trader's own finances page (`wallet.index`) and the full transfer UI: entry button on the trust balance card, modal with recipient check, amount, 2FA, ConfirmModal confirmation, and partial Inertia reload after success.

## Step 9 — Inertia props (`WalletController`)

On `wallet.index` only, when `isTraderOwnFinancesPage()` is true, the controller passes `traderBalanceTransfer` to `Wallet/Index`:

| Key | Type | Meaning |
|-----|------|---------|
| `available` | bool | Trader role, `team_leader_id` set, not archived/blocked (`canTraderBalanceTransfer`) |
| `trust_balance` | string | `wallet.trust_balance->toPrecision()` for «Перевести всё» truncation on the client |
| `has_2fa` | bool | `google2fa_secret !== null` |

On other wallet surfaces (admin user wallet, Team Leader read-only trader finances, merchant/agent/provider) the prop is `null`.

Private helpers added: `traderBalanceTransferProps()`, `canTraderBalanceTransfer()`.

## Steps 10–15 — Vue UI

### Files

| File | Role |
|------|------|
| `resources/js/Modals/Wallet/TraderBalanceTransferModal.vue` | Modal flow: check recipient (GET), confirm (ConfirmModal), POST store, reload |
| `resources/js/utils/truncateTrustBalanceForTransfer.js` | Truncate trust balance down to 2 decimals for «Перевести всё» |
| `resources/js/Pages/Wallet/Partials/TrustBalance.vue` | «Перевести средства» button when `traderBalanceTransfer.available` and not admin view |
| `resources/js/Pages/Wallet/Index.vue` | Passes prop to TrustBalance; mounts modal |
| `resources/js/store/modal.js` | `traderBalanceTransfer` modal + `openTraderBalanceTransferModal()` |

### Modal behavior

- Recipient: `login` query → `route('wallet.trader-transfer.recipient')`; preview shows Dicebear avatar + login; preview cleared when login input changes.
- Amount: max 2 decimal places via input sanitization; «Перевести всё» uses `truncateTrustBalanceForTransfer(trust_balance)` from page props.
- 2FA field shown when `has_2fa`; POST includes `one_time_password` when required.
- Final submit gated by `ConfirmModal` («Подтвердите перевод»).
- Success: close modal, `router.reload({ only: ['walletStats', 'invoices', 'transactions', 'traderBalanceTransfer'], preserveScroll: true })`.
- Errors: Laravel `errors` on fields; service `message` on 422 mapped to recipient/amount/2FA as appropriate.

### Entry point

Button label «Перевести средства» (short «Перевести» on mobile) in the trust balance card join group, before withdraw/deposit. Hidden when `walletSurfaces` admin view or `available` is false.

## Remaining

- Step 16: Pint run on changed PHP (done in session).
- Step 17: Automated tests when explicitly requested.
- Manual UI verification per implementation plan.

## Feature status

End-to-end trader transfer (API + UI) is **shipped** pending tests and manual QA.
