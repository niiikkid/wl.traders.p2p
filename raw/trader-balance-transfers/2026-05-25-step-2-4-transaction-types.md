# Trader Balance Transfer — Steps 2–4 (TransactionType + Localization)

> Source: Implementation session (Cursor agent), repository `p2p.cti`
> Collected: 2026-05-25
> Published: Unknown

## Summary

Steps 2–4 of the trader balance transfer plan were implemented in code. No API, service, routes, or UI yet.

## Changes

### `app/Enums/TransactionType.php`

- Added `TRANSFER_TO_TRADER = 'transfer_to_trader'` (OUT group).
- Added `TRANSFER_FROM_TRADER = 'transfer_from_trader'` (IN group).
- `direction()` maps `TRANSFER_TO_TRADER` → `TransactionDirection::OUT`, `TRANSFER_FROM_TRADER` → `TransactionDirection::IN`.

### `lang/ru/transaction-type.php`

- `transfer_to_trader` → `Перевод трейдеру`
- `transfer_from_trader` → `Перевод от трейдера`

Labels surface in wallet operation history via `TransactionResource` (`type_name` = `trans('transaction-type.'.$type)`).

## Not changed

- No `TraderBalanceTransferService`, controller, or routes.
- No migration (transaction `type` is string-backed enum in PHP, not a DB enum).
- Steps 5–15 still pending.

## Next

Steps 5–7: Form Requests, `TraderBalanceTransferService`, controller (`recipient`, `store`).
