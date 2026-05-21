# Telegram Chat Dispute Automation Requirements

> Source: User conversation in Cursor
> Collected: 2026-05-21
> Published: Unknown

## Feature Context

We need a new Telegram bot that can be added to different Telegram chats. The bot receives messages from chats, sends them into the application, and the application parses those messages to perform actions.

The initial use case is merchant chats where merchants send dispute messages. A dispute message normally contains:

- A receipt file, either image or PDF.
- An order UUID from our system.

When the application identifies such a message, it must automatically open a dispute for the matched deal.

## Scope

- Only `Order` is supported.
- Cascade deals and Cascade functionality must be ignored completely.
- `orders.uuid` is globally unique in the system.
- The bot operates from any active chat. There is no merchant binding check for now.
- The first implementation is for disputes, but naming should stay professional and reasonably universal for future chat-based automation use cases.

## Message Recognition

- Strictly parse UUIDs by regex.
- Search for the UUID in the main message text/caption for now. Other sources such as replies/forwarded messages can be added later.
- The message can contain noise and unrelated UUIDs.
- Find UUID candidates, then query `Order::whereIn('uuid', ...)`.
- If exactly one matching `Order` is found, use it.
- If more than one matching `Order` is found, mark the message as an error because the message is ambiguous.
- If no matching `Order` is found, save the message and mark it as an error with a reason.

## Files

- Allowed file extensions/MIME types: `jpg`, `jpeg`, `png`, `pdf`.
- Maximum file size: 5 MB.
- Unwanted files must be rejected strictly.
- Files must not be stored in the database.
- Store only file names/metadata in the database.
- Store files in a private non-public folder.
- Files must be served in the admin UI through a protected admin endpoint, not directly from public storage.

## Dispute Handling

- When a valid dispute message is detected, open a dispute for the matched `Order`.
- Use the received image/PDF as the dispute receipt, similarly to the existing H2H API flow.
- If a dispute already exists for the order, save the message and mark it as `duplicate`.
- On successful dispute creation, the bot must send a Telegram message to the chat, professionally worded, for example:
  - `Спор открыт. UUID сделки: <uuid>`
- For all other cases, the bot should not answer yet. More responses may be added later.

## Bot Settings

- This is a new, separate bot from the current notification bot.
- Bot settings must be managed through the admin UI, not through `.env`.
- Bot token must be stored encrypted in the database.
- Use one webhook URL.
- The admin page should have a button to set/update the webhook. The webhook URL itself does not need to be entered manually on the page.
- Use Telegram webhook `secret_token` and verify the `X-Telegram-Bot-Api-Secret-Token` header.

## Admin UI

- Admin-only, `Super Admin` access.
- One admin page for the feature.
- Bot settings can be placed in a modal.
- The page shows all chats where the bot has received messages.
- A new chat appears when a message arrives from that chat.
- New chats start in `pending_moderation`.
- Chat statuses:
  - `pending_moderation`
  - `active`
  - `disabled`
  - `archived`
- Archived chats are hidden from the main list but available through an archive filter.
- Each chat can be opened to view its messages.
- Chat settings are stored on the chat model.
- Each chat has its own debug mode setting.

## Chat Message Storage

- Always save dispute-related messages and their files.
- Save non-dispute messages only if debug mode is enabled for that chat.
- Store raw Telegram payload for dispute messages always.
- Store raw Telegram payload for non-dispute messages only when debug mode is enabled.
- Message processing statuses may include:
  - `received`
  - `ignored`
  - `matched`
  - `processed`
  - `failed`
  - `duplicate`
- When debug mode is turned off for a chat, all non-dispute messages for that chat must be deleted, including their files.
- Cleanup can be asynchronous but must be safe and must not delete unrelated project files or dispute-related files.
- Show a confirmation modal before disabling debug mode because it deletes non-dispute message history.

## Models

- Use `TelegramChat`.
- Use `TelegramChatMessage`.
- Chat settings live on `TelegramChat`.

## Processing

- Webhook should be fast.
- Webhook should save/identify the update and dispatch queued work.
- Processing should be asynchronous.
- Idempotency is required:
  - By `telegram_update_id`.
  - By `chat_id + message_id`.
- No tests should be written or run for this feature unless explicitly requested later.
