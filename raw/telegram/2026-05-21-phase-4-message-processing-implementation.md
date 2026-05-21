# Phase 4 — Message Processing Implementation

> Source: Project implementation (conversation + codebase)
> Collected: 2026-05-21
> Published: 2026-05-21

## Summary

Phase 4 implements queued message processing: file extraction and download from Telegram, receipt validation, order UUID matching, dispute creation via the existing dispute service, status transitions, and optional success reply in the chat. Processing runs only for chats with status `active` and messages with status `received`.

## Components

- `ProcessTelegramChatMessageJob` — reloads message + chat; no-op unless chat `active`; delegates to `TelegramChatMessageProcessor`
- `TelegramChatMessageProcessor` — selects parser by `TelegramChat.parser_type`; registered in `AppServiceProvider` with parser list
- `StandardTelegramDisputeParser` — implements `TelegramChatMessageParserContract` for `standard_dispute`
- `TelegramChatFileService` / `TelegramChatFileServiceContract` — attachment extraction, download, validation, private storage, `UploadedFile` bridge
- `TelegramAttachmentReference` — value object for Telegram file metadata
- `TelegramChatBotService` extensions — `getFileInfo()`, `downloadFileToPath()`, `sendChatMessage()`

## Processing flow (`StandardTelegramDisputeParser`)

1. Skip unless message `status` is `received` (idempotent retries)
2. Extract UUID candidates from `text`, else `caption` (lowercased); same regex as ingestion
3. No UUID: `ignored` if `debug_enabled`, else `failed` with reason
4. `Order::whereIn('uuid', $candidates)->with('dispute')` — 0 → failed, >1 → failed (ambiguous), 1 → continue
5. Require `photo` or `document` in `raw_payload`; else failed
6. Set `matched`, link `order_id` / `detected_uuid`
7. If order already has dispute → `duplicate` (no Telegram reply)
8. Download file via `getFile` + `https://api.telegram.org/file/bot<token>/<file_path>`; validate `mimes:jpeg,jpg,png,pdf`, max 5120 KB; store under `storage/app/telegram-chat-attachments/`
9. `services()->dispute()->create($order->id, $uploadedFile)` — receipt copied to `storage/receipts` by dispute service
10. Mark `processed`, link `dispute_id`, send success message; reply failure is logged only (dispute not rolled back)

## Success reply text

```
Спор открыт.
UUID сделки: <uuid>
```

## Failure reasons (Russian, admin-facing)

Examples: `UUID заказа не найден`, `Найдено несколько заказов с подходящими UUID`, `Чек (фото или документ) не найден в сообщении`, `Недопустимый тип или размер файла чека`, `Размер файла превышает 5 МБ.`

## File handling

- Photo: largest `file_size` entry from `photo` array
- Document: `document.file_id`, optional `file_name`, `mime_type`, `file_size`
- Temp download to `sys_get_temp_dir()`; validated copy to `local` disk `telegram-chat-attachments/{random32}.{ext}`
- Rejected/invalid files are not persisted (temp file deleted in `finally`)

## Contracts registered (`AppServiceProvider`)

- `TelegramChatFileServiceContract` → `TelegramChatFileService`
- `StandardTelegramDisputeParser` (injected with file service)
- `TelegramChatMessageProcessor` (iterable of parsers)
