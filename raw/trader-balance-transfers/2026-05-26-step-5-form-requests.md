# Step 5: Trader Balance Transfer Form Requests

> Source: Agent implementation session (p2p.cti codebase)
> Collected: 2026-05-26
> Published: 2026-05-26

## Goal

Complete implementation plan step 5: Form Request classes for recipient check and transfer submit endpoints.

## Files added

- `app/Http/Requests/Wallet/TraderTransfer/Concerns/AuthorizesTraderBalanceTransfer.php` — shared `authorize()` for both requests
- `app/Http/Requests/Wallet/TraderTransfer/CheckRecipientRequest.php`
- `app/Http/Requests/Wallet/TraderTransfer/StoreTransferRequest.php`

## AuthorizesTraderBalanceTransfer

`authorize()` returns true only when:

- authenticated user has role `Trader`;
- `team_leader_id` is not null;
- `archived_at` and `banned_at` are null.

Returns false otherwise (403 on failed authorize).

## CheckRecipientRequest

- Input: `login` (required string, max 255, trimmed in `prepareForValidation`).
- Helper: `recipientLogin(): string`.
- Russian validation messages for `login.required`.

## StoreTransferRequest

- Inputs: `recipient_login`, `amount`, optional `one_time_password`.
- `amount`: required string, regex `^\d+(\.\d{1,2})?$`; `after()` rejects zero/invalid via `Money::fromPrecision` + `greaterThanZero()`.
- `one_time_password`: `Rule::requiredIf` when sender has `google2fa_secret`; validated in `after()` with `pragmarx.google2fa` OTP compare (same pattern as `Check2FACodeController`, no `user_2fa_passed` session).
- Helpers: `recipientLogin(): string`, `amountMoney(): Money` (USDT).
- Russian messages aligned with wiki copy (amount format, 2FA errors).

## Not in scope yet

- `TraderBalanceTransferService` (step 6)
- Controller and routes `wallet.trader-transfer.*` (steps 7–8)
- Vue modal / Inertia props (steps 9–15)

## Next step

Step 6: `TraderBalanceTransferService` — recipient lookup scoped by Team Leader, wallet locks, atomic debit/credit. Balance and recipient business rules stay in service; request layer already covers sender eligibility, amount format, and 2FA.
