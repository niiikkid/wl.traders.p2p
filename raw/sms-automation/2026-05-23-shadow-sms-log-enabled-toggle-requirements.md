# Shadow SMS Log — Enable/Disable Toggle

> Source: Product conversation (Cursor), amendment to shadow SMS log feature
> Collected: 2026-05-23
> Published: Unknown

## Requirement

On the **same admin page** as the shadow log table («Теневой лог»), provide a control (toggle or button — implementation choice) to turn shadow logging on or off globally.

| State | Behavior |
|-------|----------|
| **Enabled** | Filtered messages (stop list, stop word, max length > 200) are written to `shadow_sms_logs` as specified in the base requirements. |
| **Disabled** | Filtering at `SmsController` is unchanged (messages still do not enter `sms_logs`), but **no** shadow log job is dispatched and **no** rows are inserted. |

## Constraints

- Must not affect main automation: `HandleSmsJob`, `sms_logs`, parsing, stop-list/stop-word filtering logic.
- Toggle is **admin-only**, on the shadow log page (not on trader UI).
- Setting is **global** (one switch for the whole system), not per user/device.
- Persist preference in database (project `settings` table via `SettingsService`), not only in browser session.
- When disabled, existing rows in `shadow_sms_logs` remain; only new writes stop.

## UX

- Control visible on `Admin/ShadowSmsLog/Index` (e.g. DaisyUI `toggle` in header/toolbar near «Удалить всё»).
- Label should make state obvious (e.g. «Запись в теневой лог»).
- Save via dedicated admin endpoint (PATCH), block control while `processing`.

## Default

On first install (`app:install-settings`): **enabled** (`1`), so deployed behavior matches the original spec until an admin turns it off.
