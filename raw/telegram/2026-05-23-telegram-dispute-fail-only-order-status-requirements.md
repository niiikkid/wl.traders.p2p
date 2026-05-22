# Telegram Dispute Opening — Fail Order Status Only

> Source: User conversation in Cursor (implementation task)
> Collected: 2026-05-23
> Published: Unknown

## Requirement

Telegram chat dispute automation must open disputes only when the matched `Order` is in status `fail` and has no existing dispute.

This rule applies **only** to messages processed by `StandardTelegramDisputeParser`. Other channels (H2H API, payment link, Admin/Support/Analyst UI, `DisputeService::create()` in general) are unchanged.

`Order` sub-status does not matter; only top-level `order.status` is checked.

## Behavior

After UUID match, receipt attachment, and duplicate-dispute check:

### Order already has a dispute

- Unchanged: mark message `duplicate`, send duplicate reply.

### Order status `success`

- Do not download attachment or call `dispute()->create()`.
- Mark message `failed` with reason: «Сделка успешно завершена. По ней нельзя открыть спор.»
- Send Telegram reply (to source message):

```text
Сделка успешно завершена. По ней нельзя открыть спор.
UUID сделки: <uuid>
```

### Order status `pending`

- Do not download attachment or call `dispute()->create()`.
- Mark message `failed` with reason: «Сделка ещё обрабатывается. По ней нельзя открыть спор.»
- Send Telegram reply:

```text
Сделка ещё обрабатывается. По ней нельзя открыть спор.
UUID сделки: <uuid>
```

### Order status `fail` (no dispute)

- Unchanged: download receipt, `services()->dispute()->create()`, mark `processed`, send «Спор открыт» reply.
- `DisputeService::create()` still reopens the finished order with `WAITING_FOR_DISPUTE_TO_BE_RESOLVED` for fail orders.

### Other statuses (defensive)

- Mark `failed` without Telegram reply if status is not fail/success/pending.

## Implementation

- File: `app/Services/Telegram/Parsers/StandardTelegramDisputeParser.php`
- Methods: `sendSuccessOrderDisputeNotAllowedReply()`, `sendPendingOrderDisputeNotAllowedReply()`
- Uses existing `sendChatReply()` with `reply_parameters` like success/duplicate replies.

## Explicit non-scope

- No changes to `DisputeService::create()` global behavior (pending auto-cancel, success reopen, etc.).
- No Cascade changes.
- No wiki update requested by user at task time (wiki ingest requested separately).
