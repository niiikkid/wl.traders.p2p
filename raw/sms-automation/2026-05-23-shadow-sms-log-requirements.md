# Shadow SMS Log (Теневой лог) — Requirements

> Source: Product conversation (Cursor), automation/SMS filtering feature design
> Collected: 2026-05-23
> Published: Unknown

## Goal

Persist SMS/push payloads that are **rejected before** the main automation pipeline (`HandleSmsJob` → `sms_logs`), so admins can inspect why a message was filtered without affecting existing parsing, logging, or order matching.

## Non-Goals

- Do not change `HandleSmsJob`, `SmsService`, or `SmsLog` creation behavior.
- No soft delete, no archival, no TTL/auto-cleanup.
- No trader access; admin only.
- Duplicate rows are acceptable (no deduplication).

## When to Log (Single Entry Point)

All shadow logging happens in `POST /api/app/sms` (`App\Http\Controllers\API\APP\SmsController::store`) **before** `HandleSmsJob::dispatch`, in three cases:

1. **Sender stop list** — normalized `sender` is in `sender_stop_lists` (cached key `sender_stop_list`, 10 minutes).
2. **Stop word** — `Parser` detects a configured stop word in the message (cached key `sms_stop_words`, 60 seconds).
3. **Max message length** — `mb_strlen(message) > 200` (`MAX_INCOMING_SMS_MESSAGE_LENGTH`).

Messages that pass all three checks continue into the existing queue/job flow and are stored in `sms_logs` as today (even if parsing later returns null).

**Not logged:** device not connected (401), validation failures, idempotency replays (middleware may prevent duplicate API hits — acceptable).

## Persistence

- New model: `ShadowSmsLog`, table `shadow_sms_logs`.
- Store API fields as received: `sender`, `message`, `timestamp`, `type` (`SmsType`).
- Bind `user_id`, `user_device_id` from resolved device.
- `filter_reason` enum (machine value + Russian label in UI).
- Detail columns per reason:
  - Stop list: `matched_sender` (normalized sender used for the check).
  - Stop word: `matched_stop_word` (the word that matched).
  - Max length: `message_length` (integer at rejection time).
- Hard delete entire table via admin «Удалить всё» with `ConfirmModal` (single confirmation phrase).

## Write Path

- Dispatch async job to existing **`sms`** queue (same as `HandleSmsJob`).
- If shadow log insert fails, **silently ignore**; API still returns `success`.
- No impact on main flow timing beyond lightweight job dispatch.

## Admin UI

- New page in **Автоматика** group (4th page alongside Сообщения, Приложение, Устройства).
- Same cross-navigation button strip on all four pages (no breadcrumbs).
- Pagination + filters:
  - Login → `users.email` LIKE
  - Device → `user_devices.name` LIKE
  - Sender → `shadow_sms_logs.sender` LIKE
  - Message → `shadow_sms_logs.message` LIKE
- Display: login (email), device name, sender, message, type, timestamp, filter reason with explicit detail text.
- «Удалить всё» — truncate/delete all `shadow_sms_logs` only.

## Parser Change (Isolated)

Add a method that returns the **matched stop word** (e.g. `findMatchedStopWord(string $message): ?string`) without changing existing `hasStopWord()` behavior used elsewhere in `Parser::parse()`.

## Routes (Suggested)

- `GET admin/shadow-sms-logs` → index
- `DELETE admin/shadow-sms-logs` → destroy all
- Menu highlight: include `admin.shadow-sms-logs.*` in Автоматика active state
