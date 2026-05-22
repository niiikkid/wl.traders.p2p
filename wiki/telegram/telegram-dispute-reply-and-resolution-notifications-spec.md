# Telegram Dispute Reply and Resolution Notifications Specification

> Sources: User conversation, 2026-05-22; Telegram Bot API documentation, 2026-05-22; repository exploration, 2026-05-22; immediate reply implementation, 2026-05-22; resolution notifications implementation, 2026-05-22; fail-only order status gate (opening replies), 2026-05-23
> Raw: [Telegram Dispute Reply and Resolution Notifications Requirements](../../raw/telegram/2026-05-22-telegram-dispute-reply-and-resolution-notifications-requirements.md); [Telegram Dispute Immediate Reply Implementation](../../raw/telegram/2026-05-22-telegram-dispute-immediate-reply-implementation.md); [Telegram Dispute Resolution Notifications Implementation](../../raw/telegram/2026-05-22-telegram-dispute-resolution-notifications-implementation.md); [Telegram Dispute Fail-Only Order Status Requirements](../../raw/telegram/2026-05-23-telegram-dispute-fail-only-order-status-requirements.md)
> Updated: 2026-05-23

## Overview

This specification extends the existing Telegram chat dispute automation in two areas: immediate bot responses must be sent as replies to the source Telegram message, and disputes opened from Telegram must send asynchronous resolution notifications back to that same source message after the dispute is accepted or rejected. Rejected-dispute notifications should attach the bank/card statement in the same Telegram reply message when possible, with a text-only fallback when the file cannot be sent.

**Feature 1 (immediate opening/duplicate replies) is implemented** in the codebase as of 2026-05-22. **Feature 2 (accept/reject resolution notifications) is implemented** as of 2026-05-22 (Phases 1, 3–4 complete; Phase 5 manual verification pending).

## Product Scope

The feature applies only to classic `Order` disputes created through Telegram chat automation:

- a Telegram message contains an `Order` UUID and receipt attachment;
- `StandardTelegramDisputeParser` opens the dispute only when the matched order is in status `fail` and has no existing dispute (see [Telegram Chat Dispute Automation Plan](telegram-chat-dispute-automation-plan.md));
- on success/pending orders the parser sends a rejection reply and does not call `services()->dispute()->create()`;
- when opened, the parser calls `services()->dispute()->create()` and `telegram_chat_messages.dispute_id` links the source Telegram message to the created `disputes.id`;
- later, a web user accepts or rejects that same dispute through the existing UI routes.

The feature does not apply to:

- disputes created manually from the admin panel;
- H2H/API/V2-created disputes;
- Cascade deal disputes;
- disputes without a linked `TelegramChatMessage`;
- dispute rollback.

## User-Facing Behavior

### Opening and Duplicate Replies

The existing immediate bot responses must become replies to the original Telegram message:

- successful opening (`fail` order): `Спор открыт.\nUUID сделки: <uuid>`;
- duplicate: `Спор по этой сделке уже открыт.\nUUID сделки: <uuid>\nПовторно спор не создан — это дубликат.`;
- order `success`: `По сделке нельзя открыть спор.\nСделка успешно завершена.\nUUID сделки: <uuid>` (implemented 2026-05-23);
- order `pending`: `По сделке нельзя открыть спор.\nСделка ещё обрабатывается.\nUUID сделки: <uuid>` (implemented 2026-05-23).

All responses must target the same Telegram chat and reply to the same `telegram_message_id` stored on the source `TelegramChatMessage`.

If Telegram cannot find the original message, the bot should still be allowed to send the status message without breaking processing. Telegram Bot API supports this through `reply_parameters.allow_sending_without_reply = true`.

### Accepted Dispute Notification

When a Telegram-originated dispute is accepted, send one asynchronous Telegram reply to the original message:

```text
Спор принят.
UUID сделки: <uuid>
```

This message is informational only. If it fails, the dispute remains accepted.

### Rejected Dispute Notification

When a Telegram-originated dispute is rejected, send one asynchronous Telegram reply to the original message. The preferred message is a single document message:

- Telegram method: `sendDocument`;
- document: the `disputes.bank_statement` file from `storage/dispute-bank-statements`;
- caption:

```text
Спор отклонён.
UUID сделки: <uuid>
```

The document message must reply to the original Telegram message using the same reply mechanism as text messages.

If the bank statement is missing in the database, missing on disk, unreadable, or Telegram rejects the upload, the job should fall back to a text reply:

```text
Спор отклонён.
UUID сделки: <uuid>
Не удалось загрузить выписку.
```

The fallback should be best-effort. If the fallback also fails, log the failure and do not change dispute state.

Special case for reason code `wrong_details` (`Неверные реквизиты`): if no statement is attached by design, send a text-only reply:

```text
Спор отклонён.
UUID сделки: <uuid>
Причина: Неверные реквизиты
Выписка не требуется.
```

## Existing Code Anchors

Current Telegram automation already has most of the identity data needed:

- `TelegramChatMessage.telegram_chat_id` links to local `telegram_chats.id`;
- `TelegramChatMessage.telegram_message_id` stores the Telegram API message id that must be replied to;
- `TelegramChat.telegram_chat_id` stores the Telegram API chat id;
- `TelegramChatMessage.dispute_id` is set when `StandardTelegramDisputeParser` successfully opens a dispute;
- `ProcessTelegramChatMessageJob` already uses queue `telegram-chat-automation`.

Current immediate responses are sent by:

- `StandardTelegramDisputeParser::sendSuccessReply()`;
- `StandardTelegramDisputeParser::sendDuplicateReply()`;
- `StandardTelegramDisputeParser::sendChatReply()`;
- `TelegramChatBotService::sendChatMessage()`.

Current dispute resolution points are:

- `DisputeService::accept(int $disputeID)`;
- `DisputeService::cancel(int $disputeID, DisputeCancelReasonCode $reasonCode, ?string $customReason = null, ?UploadedFile $bankStatement = null)`.

The resolution notification should be dispatched after the transaction commits, not before the dispute status and bank statement are durably stored.

## Telegram API Requirements

Use modern Telegram Bot API reply parameters rather than only the legacy `reply_to_message_id` field:

```php
'reply_parameters' => [
    'message_id' => $replyToMessageId,
    'allow_sending_without_reply' => true,
],
```

For text messages, call `sendMessage` with:

- `chat_id`;
- `text`;
- optional `reply_parameters`.

For rejected disputes with a statement, call `sendDocument` with:

- `chat_id`;
- multipart `document`;
- `caption`;
- optional `reply_parameters`.

`sendDocument` supports captions and `reply_parameters`, so the rejected notification can be a single Telegram message containing both the text and attached statement.

## Backend Design

### Extend `TelegramChatBotServiceContract`

Add optional reply support to text sending:

```php
public function sendChatMessage(
    string $chatId,
    string $text,
    ?int $replyToMessageId = null,
): void;
```

Add document sending:

```php
public function sendChatDocument(
    string $chatId,
    string $documentPath,
    ?string $caption = null,
    ?int $replyToMessageId = null,
): void;
```

Implementation notes:

- keep token lookup and error handling consistent with `sendChatMessage()`;
- use the existing configured Telegram proxy behavior through the service's HTTP client;
- upload local files with multipart/form-data;
- throw `TelegramChatBotException` on failed Telegram response;
- do not expose bot token or local file paths in logs.

### Immediate Reply Update

Update `StandardTelegramDisputeParser` so it passes the source Telegram message id into bot responses:

- in the normal success path, call `sendSuccessReply($apiChatId, $message->telegram_message_id, $order->uuid)`;
- in duplicate paths, call `sendDuplicateReply($apiChatId, $message->telegram_message_id, $order->uuid)`;
- cast `telegram_message_id` safely to integer before using it as `reply_parameters.message_id`;
- preserve existing logging-only behavior when Telegram reply sending fails.

No database changes are needed for immediate replies.

### Resolution Notification Job

Create a queued job, for example `SendTelegramDisputeResolutionNotificationJob`.

Recommended constructor:

```php
public function __construct(
    private readonly int $disputeId,
    private readonly DisputeStatus $status,
) {
    $this->afterCommit();
    $this->onQueue('telegram-chat-automation');
}
```

The job should:

1. Load the dispute with `order`.
2. Find the source Telegram message:
   - `TelegramChatMessage::query()->with('telegramChat')->where('dispute_id', $dispute->id)->first()`.
3. Return quietly if no source Telegram message exists.
4. Return quietly if the source message has no `telegramChat`.
5. Return quietly if the status is not `accepted` or `canceled`.
6. Build the text using `order.uuid`.
7. For accepted disputes, send a text reply.
8. For canceled disputes, try to send a document reply with the bank statement and caption.
9. If reason code is `wrong_details` and there is no statement by design, send text with reason and `Выписка не требуется.`
10. Otherwise, if statement cannot be sent, send the fallback text with `Не удалось загрузить выписку.`
10. Log failures without throwing if the notification failure should not be retried.

Retry policy depends on operational preference:

- if transient Telegram failures should retry, allow `TelegramChatBotException` to bubble after logging enough context;
- if dispute resolution actions must never create queue noise from Telegram outages, catch and log all Telegram exceptions.

Given the product requirement "do not break accept/cancel", the web action is protected either way because the job is asynchronous and `afterCommit()`.

### Dispatch Points

Dispatch the job after accepted/canceled status is persisted:

- from `DisputeService::accept()` after successful status update;
- from `DisputeService::cancel()` after successful status update and bank statement storage.

Because both methods run inside `Transaction::run()`, the job must use `afterCommit()` so it sees:

- final `disputes.status`;
- final `disputes.bank_statement`;
- linked order data.

Do not dispatch from controllers. Controllers are role-specific and should stay thin; the service owns the domain state change.

## File Handling for Rejected Notifications

Bank statements are stored in:

```text
storage/dispute-bank-statements/<disputes.bank_statement>
```

The job should build the path from the stored filename and verify:

- `bank_statement` is not null;
- the resolved path is inside the expected directory;
- the file exists and is readable.

If any check fails, send the text fallback. Do not attempt to re-read request upload objects in the job; only persisted files should be used.

The document caption must stay under Telegram's caption limit. The proposed two-line text is well below the limit.

## Idempotency and Duplicate Notifications

The first implementation can be best-effort and may send duplicate resolution notifications if the job is manually retried after a successful send. If strict idempotency is required later, add explicit tracking fields rather than inferring from logs.

Optional future fields on `telegram_chat_messages`:

- `resolution_notified_at`;
- `resolution_notification_status`;
- `resolution_notification_error`.

Do not add these fields in the first implementation unless duplicate prevention becomes a hard requirement.

## Error Handling and Logging

Telegram notification failures must not affect dispute state.

Log enough context to diagnose failures:

- `dispute_id`;
- `order_id`;
- `order_uuid`;
- local `telegram_chat_message_id`;
- API `telegram_chat_id`;
- API `telegram_message_id`;
- status being notified;
- exception class and message.

Do not log:

- bot token;
- webhook secret;
- absolute bank statement path unless needed for local diagnostics;
- raw file contents.

## Security and Authorization

No new user-facing route is required. The job sends files directly to Telegram using the bot token, so web authorization gates are not involved in delivery.

Security boundary:

- only send bank statements for disputes that are linked to a Telegram-originated message;
- only send the statement back to the same Telegram chat that opened the dispute;
- never broadcast dispute statements to unrelated chats;
- keep the bank statement storage private.

This inherits the existing operational assumption that the Telegram chat is an allowed merchant/support dispute channel once activated in the admin UI.

## Implementation Status

| Phase | Scope | Status |
|-------|--------|--------|
| 1 | Bot service reply support (`sendChatMessage` + `reply_parameters`) | **Done** (2026-05-22) |
| 1 | `sendChatDocument()` for resolution notifications | **Done** (2026-05-22) |
| 2 | Immediate success and duplicate replies | **Done** (2026-05-22) |
| 3 | Resolution notification job | **Done** (2026-05-22) |
| 4 | Dispatch from `DisputeService` | **Done** (2026-05-22) |
| 5 | Manual / programmatic verification | **Partial** — code complete; live Telegram checklist pending |

### Implemented artifacts (Feature 1)

- `TelegramChatBotServiceContract::sendChatMessage(string $chatId, string $text, ?int $replyToMessageId = null)`
- `TelegramChatBotService::buildReplyParameters()` — `message_id` + `allow_sending_without_reply: true`
- `StandardTelegramDisputeParser::resolveReplyToMessageId()` — digit-only string → int; otherwise no reply target
- Success and both duplicate paths pass `$message->telegram_message_id` into reply helpers

### Implemented artifacts (Feature 2)

- `TelegramChatBotServiceContract::sendChatDocument(string $chatId, string $documentPath, ?string $caption, ?int $replyToMessageId)` — multipart `sendDocument`, `reply_parameters` JSON-encoded for multipart
- `SendTelegramDisputeResolutionNotificationJob` — queue `telegram-chat-automation`, `afterCommit()`, accepted text / canceled document with bank-statement path guard and text fallback
- `SendTelegramDisputeResolutionNotificationJob` late update — `wrong_details` + empty statement sends text-only notification: `Причина: Неверные реквизиты` + `Выписка не требуется.`
- `DisputeService::accept()` / `cancel()` — dispatch job after successful update; controllers unchanged

## Implementation Phases

### Phase 1 — Bot Service Reply Support — Done

Deliverables:

- [x] extend `TelegramChatBotServiceContract::sendChatMessage()` with optional reply message id;
- [x] implement `reply_parameters` in `TelegramChatBotService::sendChatMessage()`;
- [x] add `sendChatDocument()` to contract and service;
- [x] keep existing call sites working by making the new reply parameter optional.

Acceptance criteria:

- [x] existing plain text sends still work;
- [x] text sends can reply to a Telegram message id;
- [x] document sends can include a caption and reply to a Telegram message id;
- [x] Telegram API failures still throw `TelegramChatBotException`.

### Phase 2 — Immediate Success and Duplicate Replies — Done

Deliverables:

- [x] update `StandardTelegramDisputeParser` to pass source `telegram_message_id` to success replies;
- [x] update duplicate replies in both duplicate branches;
- [x] preserve current text contents;
- [x] keep Telegram send failures as warnings only.

Acceptance criteria:

- [x] successful dispute-open bot response is sent with `reply_parameters` to the triggering message (manual UI confirmation recommended);
- [x] duplicate bot response is sent with `reply_parameters` in both duplicate branches;
- [x] dispute processing still succeeds even if the reply cannot be sent.

### Phase 3 — Resolution Notification Job — Done

Deliverables:

- [x] create `SendTelegramDisputeResolutionNotificationJob`;
- [x] use queue `telegram-chat-automation`;
- [x] use `afterCommit()`;
- [x] load linked `TelegramChatMessage` by `dispute_id`;
- [x] send accepted text reply;
- [x] send canceled document reply with bank statement and caption;
- [x] send fallback text reply when the statement cannot be attached;
- [x] log all failures without breaking dispute state.

Acceptance criteria:

- [x] only Telegram-originated disputes produce resolution notifications (code path; live chat confirmation pending);
- [x] accepted dispute sends `Спор принят` reply with UUID (code path; live chat confirmation pending);
- [x] rejected dispute sends one document reply with caption and statement when possible (code path; live chat confirmation pending);
- [x] rejected dispute sends text fallback when statement upload is unavailable (code path; live chat confirmation pending);
- [x] rollback sends nothing.

### Phase 4 — Dispatch from Dispute Service — Done

Deliverables:

- [x] dispatch resolution job from `DisputeService::accept()` after status update;
- [x] dispatch resolution job from `DisputeService::cancel()` after status/bank statement update;
- [x] keep controllers unchanged.

Acceptance criteria:

- [x] all role surfaces that call the same service methods trigger notification for Telegram-originated disputes;
- [x] manually created disputes do not send Telegram notifications because no linked source message exists;
- [x] accept/cancel HTTP actions do not fail because of Telegram notification errors.

### Phase 5 — Verification

Manual verification checklist:

- open a dispute from Telegram with receipt + UUID and confirm the bot success message is a reply;
- send the same Telegram message scenario for an order that already has a dispute and confirm duplicate response is a reply;
- accept a Telegram-originated dispute and confirm `Спор принят` is sent as a reply to the original Telegram message;
- reject a Telegram-originated dispute with a valid bank statement and confirm a single document message is sent as a reply with caption;
- reject with `wrong_details` and no statement, confirm text-only reply includes `Причина: Неверные реквизиты` and `Выписка не требуется.`;
- simulate missing statement file and confirm fallback text includes `Не удалось загрузить выписку.`;
- accept/reject a manually created dispute and confirm no Telegram message is sent;
- rollback a dispute and confirm no Telegram message is sent.

Programmatic checks, if explicitly requested:

- focused PHPUnit test for job no-op when no Telegram source message exists;
- focused PHPUnit test for accepted notification payload;
- focused PHPUnit test for rejected notification document path and fallback;
- service-level test or fake HTTP test for `reply_parameters` and `sendDocument`.

## Open Implementation Notes

- The first implementation should not add notification status columns unless duplicate resolution notifications become a hard product problem.
- If future business rules require merchant-bound chats, resolution notifications should validate the chat-merchant relation before sending statement files.
- If Telegram topics are later supported, the same design may need `message_thread_id` preservation from the source update.

## See Also

- [Telegram Chat Dispute Automation Plan](telegram-chat-dispute-automation-plan.md)
- [Dispute Bank Statement Implementation Plan](../dispute-bank-statements/dispute-bank-statement-implementation-plan.md)
