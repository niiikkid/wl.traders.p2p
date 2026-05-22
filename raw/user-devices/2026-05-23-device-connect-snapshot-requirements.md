# Device Connect Snapshot — Requirements

> Source: User conversation (Cursor), product specification
> Collected: 2026-05-23
> Published: Unknown

## Context

The mobile automation app calls `POST /api/app/device/connect` with structured device fields (`android_id`, `device_model`, etc.). Admins need a full dump of everything the app could collect about the device for debugging and support.

## API (`POST /api/app/device/connect`)

- Add a new **required** body field with a professional name chosen in implementation (plan: `device_connect_snapshot`).
- Value is a **string** containing JSON exactly as produced by the client (store verbatim in the database; no server-side reshaping).
- Snapshot is sent **only on connect**, not on `device/ping`.
- On **every** successful connect (including reconnect with the same `android_id`), **overwrite** the stored snapshot with the new payload.
- If payload size is **greater than 1 MiB** (1 048 576 bytes), **do not persist** the snapshot (other connect fields may still update per existing rules).
- Do **not** return the snapshot in the app API response (`UserDeviceResource` for `/api/app/*`).

## Database

- New nullable `longText` column on `user_devices` for the snapshot string.
- Historical rows remain `NULL` until the device connects again with a compliant payload.

## Admin UI (`/admin/devices`)

- **Admin only** — traders do not see the snapshot.
- Do **not** include snapshot in the paginated devices list (Inertia index).
- Add a control in the table row (e.g. right side) that opens a **wide modal**.
- Load snapshot **lazily** via a dedicated admin JSON endpoint when the modal opens.
- Display: **pretty-printed JSON** when valid; otherwise show **plain text** as stored.
- Modal includes **Copy** — copies the **raw stored string** (not the formatted view).
- No redaction or sensitivity masking.

## Non-goals

- No snapshot on ping.
- No trader-facing UI for this field.
- No changes to Telegram or other automation unrelated to device connect.
