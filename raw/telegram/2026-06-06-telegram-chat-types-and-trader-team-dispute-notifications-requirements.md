# Telegram Chat Types and Trader Team Dispute Notifications Requirements

> Source: User conversation in Cursor, 2026-06-06
> Collected: 2026-06-06
> Published: Unknown

The existing Telegram chat automation feature must be extended so Telegram chats are no longer assumed to be dispute-processing chats by default.

When a new Telegram chat is created from an incoming webhook, it must remain unassigned: no chat type / functional mode should be selected by default. The current dispute automation parser must not run for unassigned chats. A Super Admin must explicitly configure the chat in the admin UI and choose which functionality applies to it. After choosing the current dispute-processing mode, all existing dispute message logic must continue to work exactly as it works now.

The existing user Telegram notification bot must remain independent and unchanged. The new feature must not affect old Telegram user notifications.

Two chat functions are required:

1. Current dispute-processing chat.
2. Trader team chat, named "Команда трейдеров".

For trader team chats, the admin UI must allow Super Admins to select multiple trader users for a Telegram chat. A trader can belong to multiple Telegram team chats. Search must be backend-driven. For each trader added to a chat, there is an optional Telegram username/tag value. It may be stored without `@`; the UI or service can normalize it for mentions.

When any new dispute is created in the system, the system must check whether the dispute trader belongs to one or more trader team Telegram chats. If the trader belongs to chats, the system sends a Telegram message to every matching chat. If a Telegram username/tag is configured for that trader in that chat, the message must mention the trader so Telegram notifies them.

This applies to all disputes without restriction. If no trader team chat exists for the trader, nothing is sent.

Message cadence:

1. Immediately after dispute creation: send a first notification that a new dispute was opened and needs processing.
2. After 15 minutes: if the dispute is still open, send a stronger professional reminder asking the trader to process the dispute urgently.
3. After that: while the dispute remains open, send reminders once per hour.

When the dispute is accepted/canceled/closed and is no longer open, reminders must stop.

Telegram delivery must be asynchronous through queues. It may use the existing `telegram-chat-automation` queue. The feature must be safe and reliable: failures in this notification feature must not break dispute creation or the rest of the project. Telegram send failures should be logged and not rolled back into the core business flow.

Notification message content should contain only UUID/text. No links are required.

Answers to clarification questions:

1. New chat default: `pending_moderation`, but no selected type/parser.
2. Existing dispute mode must keep current `active/disabled/debug` behavior after explicit selection.
3. Second chat type name: "Команда трейдеров".
4. A trader can be in multiple chats.
5. Send notifications to all matching chats.
6. Telegram username may be stored without `@`.
7. Notify for all disputes.
8. Stop reminders when dispute is no longer open.
9. Remind hourly with no time limit while open.
10. Message content: UUID/text only.
11. If no team chat for trader: do nothing.
12. Telegram failures: log only; do not break dispute flow.

Context7 documentation check:

- `irazasyed/telegram-bot-sdk` supports Laravel facade/DI usage for `sendMessage`, webhook update handling, and `sendDocument`.
- The current chat automation bot in this project does not use the SDK for chat automation; it uses direct Telegram Bot API calls through `TelegramChatBotService`. The extension can reuse that existing service and queue instead of coupling to the old notification bot SDK flow.
