# Telegram Dispute Immediate Reply Implementation (Feature 1)

> Source: Cursor agent implementation session, 2026-05-22
> Collected: 2026-05-22
> Published: Unknown

Implemented Feature 1 from the Telegram dispute reply and resolution notifications specification: immediate bot responses for successful dispute opening and duplicate detection are sent as Telegram replies to the source message.

## Scope delivered

- Phase 1 (partial): `TelegramChatBotServiceContract::sendChatMessage()` extended with optional `?int $replyToMessageId`; `TelegramChatBotService` sends `reply_parameters` with `message_id` and `allow_sending_without_reply: true` on `sendMessage`.
- Phase 2 (complete): `StandardTelegramDisputeParser` passes `telegram_message_id` into success and duplicate reply paths; `resolveReplyToMessageId()` casts digit-only string ids to int.
- Not in this change: `sendChatDocument()` (needed for rejected-dispute resolution notifications, Feature 2).

## Files changed

- `app/Contracts/TelegramChatBotServiceContract.php`
- `app/Services/Telegram/TelegramChatBotService.php` — `buildReplyParameters()` helper
- `app/Services/Telegram/Parsers/StandardTelegramDisputeParser.php` — `sendSuccessReply`, `sendDuplicateReply`, `sendChatReply`, `resolveReplyToMessageId`

## Behavior

- Success text unchanged: `Спор открыт.\nUUID сделки: <uuid>`
- Duplicate text unchanged: `Спор по этой сделке уже открыт.\nUUID сделки: <uuid>\nПовторно спор не создан — это дубликат.`
- Both duplicate branches (existing order dispute and `DisputeException` «already exists») send reply.
- Telegram send failures remain `Log::warning` only; dispute creation is not rolled back.
- If `telegram_message_id` is empty or non-numeric, message is sent without `reply_parameters` (plain chat message).

## Manual verification (pending)

- Open dispute from Telegram → bot success message is a reply to triggering message.
- Repeat for order with existing dispute → duplicate message is a reply.
