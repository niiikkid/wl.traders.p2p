# SP24 Reconciliation API and Product Requirements

> Source: User-provided HTML file `/Users/itsme/Desktop/reconciliation-api-ctinc.html` and user conversation
> Collected: 2026-06-03
> Published: Unknown

Merchant name: SP24.

The merchant provided Reconciliation API documentation for matching payments and payouts between our system and their system. The goal is to let an admin choose a merchant and period, fetch the merchant/provider-side operation list, compare it with our local operations, and show differences in counts, amounts, commissions, statuses, and balances.

The first provider integration should be SP24, but the feature must be designed as an abstract reconciliation service so future providers can be added with different APIs, authentication schemes, endpoint availability, or file-based import formats.

## SP24 API Summary

Base URL:

```text
https://api.easy-pay-24.online
```

Authentication:

```text
Authorization: Bearer <TOKEN>
```

Important security note: the real tokens shared in conversation must not be stored in the wiki, code, logs, or plaintext configuration. They should be stored only through secure admin settings or encrypted application storage.

Available methods:

- `GET /api/reconciliation/ping` — checks token and returns provider/account information.
- `GET /api/reconciliation/payouts` — returns payouts for a period.
- `GET /api/reconciliation/orders` — returns incoming payments/deposits for a period.
- `GET /api/reconciliation/balance` — returns current account balance.

Common request parameters:

- `date_from` — required, format `YYYY-MM-DD HH:MM:SS`.
- `date_to` — required, format `YYYY-MM-DD HH:MM:SS`.
- `status` — optional: `success`, `pending`, `processing`, `failed`, `expired`, `all`; default is `success`.
- `page` — optional, default `1`.
- `per_page` — optional, default `100`, maximum `500`.

Period limit: one request cannot exceed 92 days.

Filtering rules:

- `payouts` are filtered by finalization date `completed_at`.
- `orders` are filtered by creation date `created_at`.
- all amounts are returned in the account currency.

Response shape includes:

- `success`
- `provider`
- `currency`
- `type`
- `status`
- `date_from`
- `date_to`
- `data`
- `meta.page`
- `meta.per_page`
- `meta.total`
- `meta.last_page`

Payout item fields:

- `provider_withdrawal_id` — reconciliation key, provider-side payout ID.
- `internal_id` — our internal UUID from their perspective.
- `amount`
- `commission`
- `status`
- `created_at`
- `completed_at`

Order item fields:

- `provider_payment_id` — reconciliation key, provider-side payment ID.
- `internal_id`
- `amount`
- `merchant_amount`
- `commission`
- `status`
- `created_at`

Balance fields:

- `success`
- `provider`
- `currency`
- `balance`
- `as_of`

Errors:

- `200 OK` — success.
- `401 Unauthorized` — missing or invalid token.
- `422 Unprocessable Entity` — invalid dates, period longer than 92 days, invalid status, invalid `per_page`.

## Token/Account Setup Received

SP24 generated separate tokens for several provider accounts. The actual token values are intentionally redacted here.

- `Ctinc Pay`, provider/account id `23` — likely pay-in/payment account.
- `Ctinc Payout`, provider/account id `26` — likely payout account.
- `Ctinc Pay RUB`, provider/account id `34` — likely RUB pay-in/payment account.
- `Ctinc Payout RUB`, provider/account id `53` — likely RUB payout account.

Each token should be verified through `/api/reconciliation/ping` before production use. The ping response should confirm `provider`, `code`, `currency`, and `merchant_id`.

## Product Need

Admin needs a service/page for reconciliation:

- choose merchant;
- choose available reconciliation integration/strategy for that merchant;
- choose operation type: payments/orders or payouts;
- choose period;
- optionally choose status;
- run reconciliation;
- see totals and mismatches;
- inspect mismatched operations;
- export result for manual investigation.

The result should be normalized regardless of provider API differences. Providers may differ by auth type, endpoints, status names, pagination, date filtering, or whether ping/balance exists.
