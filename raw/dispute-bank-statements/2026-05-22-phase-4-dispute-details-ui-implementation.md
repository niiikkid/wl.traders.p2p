# Phase 4 Dispute Details UI Implementation

> Source: repository implementation (Cursor agent session)
> Collected: 2026-05-22
> Published: 2026-05-22

Phase 4 of dispute bank statement feature completed: expose statement URL in panel resources and add «Выписка» row in `DisputeModal.vue`.

## DisputeResource.php

- `bank_statement` => stored filename or `null`
- `bank_statement_url` => `route('disputes.bank-statement', $this->id)` when `bank_statement` is set, else `null`

## TableOrderResource.php

Embedded `dispute` object on order table rows includes the same `bank_statement` and `bank_statement_url` fields so `DisputeModal` opened from Order Index pages receives statement URLs without a separate fetch.

## DisputeModal.vue

- `showBankStatement()` — `window.open(dispute.bank_statement_url, '_blank')`
- New row below «Квитанция», visible only when `dispute.status === 'canceled'`
- Label: «Выписка»
- Button: `btn btn-xs btn-outline btn-accent`, text «Выписка», opens file in new tab
- When `bank_statement_url` is null: muted «Нет файла»

## Not changed

- API/H2H `DisputeResource`
- `CancelDisputeModal.vue`
- Cascade dispute UI

## Remaining work

- Phase 5 — role regression pass (Trader, Support, Analyst, Admin)
- Phase 6 — Pint, manual verification
