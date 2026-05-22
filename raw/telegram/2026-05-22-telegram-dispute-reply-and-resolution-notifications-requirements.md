# Telegram Dispute Reply and Resolution Notifications Requirements

> Source: User conversation in Cursor, 2026-05-22
> Collected: 2026-05-22
> Published: Unknown

The user wants two follow-up improvements for the existing Telegram chat dispute automation.

First, the bot's immediate messages about dispute creation must be Telegram replies to the original message that triggered processing. This applies both to the successful "dispute opened" message and to duplicate messages where the dispute already exists or was detected as already existing. The message should not be sent as a plain standalone chat message. It should reply to the Telegram message whose receipt and UUID opened the dispute or attempted to open it.

Second, when a Telegram-originated message opened a real dispute, the bot must later notify the same Telegram chat when that dispute is resolved. If the dispute is accepted, the bot should reply to the original Telegram message with:

```text
Спор принят.
UUID сделки: <uuid>
```

If the dispute is rejected, the bot should reply to the original Telegram message with:

```text
Спор отклонён.
UUID сделки: <uuid>
```

For rejected disputes, the bank/card statement file attached during rejection must be sent in the same Telegram reply message if technically possible. The preferred implementation is one Telegram `sendDocument` message with a caption containing the rejection text and UUID. If the statement cannot be attached, the bot should fall back to a text reply and include a short note such as "Не удалось загрузить выписку." The dispute accept/cancel web action must not fail because Telegram notification failed.

Scope clarifications:

- Duplicate and successful opening messages must both reply to the source Telegram message.
- Resolution notifications apply only to disputes that were opened from Telegram chat automation. Manually created disputes, API disputes, and other dispute sources should not send Telegram resolution notifications.
- Rollback is out of scope and must not send Telegram messages.
- Telegram notification failures should be logged but must not break dispute acceptance or rejection.
- The resolution notification must run asynchronously in a separate job.
- The job must use the same Telegram automation queue as Telegram message processing.
- Use Telegram Bot API reply support and document sending behavior as needed.
