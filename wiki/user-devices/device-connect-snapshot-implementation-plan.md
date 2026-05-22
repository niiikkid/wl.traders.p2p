# Device Connect Snapshot — Implementation Plan

> Sources: User conversation, 2026-05-23; repository exploration, 2026-05-23; implementation shipped, 2026-05-23
> Raw: [Device Connect Snapshot Requirements](../../raw/user-devices/2026-05-23-device-connect-snapshot-requirements.md); [Device Connect Snapshot Implementation](../../raw/user-devices/2026-05-23-device-connect-snapshot-implementation.md)
> Updated: 2026-05-23

## Overview

Optional `device_connect_snapshot` on `POST /api/app/device/connect` (legacy APK-safe). When sent, the server stores the string verbatim in `user_devices` (max 1 MiB via validation), never exposes it to the app API, and lets admins inspect it from `/admin/devices` via a lazy-loaded wide modal (pretty JSON, plaintext fallback, copy raw string).

**Status: implemented** (migration + API + admin UI). Automated tests not added yet.

## Product Decisions (Locked)

| Topic | Decision |
|-------|----------|
| API / DB field name | `device_connect_snapshot` (optional string, nullable in DB) |
| Backward compatibility | Omitted / `null` / empty → connect OK, snapshot column **not** updated |
| Storage | `longText`, nullable; store exactly as received when provided |
| Max size | 1 048 576 bytes when provided → **422** if exceeded (`max:1048576`) |
| When written | Only when non-empty value sent; overwrite on each such connect (incl. same `android_id`) |
| Ping | No snapshot |
| App API response | Omit snapshot |
| Admin list | No snapshot body in pagination; boolean `has_connect_snapshot` only |
| Admin read | Lazy `GET admin.devices.connect-snapshot.show` |
| UI | Column «Снимок» → modal `max-w-5xl`, `useAppClipboard` copies raw DB string |
| Sensitivity | No masking |

## Shipped Artifacts

| Layer | Path / identifier |
|-------|-------------------|
| Migration | `database/migrations/2026_05_22_212222_add_device_connect_snapshot_to_user_devices_table.php` |
| Model | `app/Models/UserDevice.php` — `$fillable`, PHPDoc |
| Contract / service | `app/Contracts/DeviceServiceContract.php`, `app/Services/Device/DeviceService.php` |
| App connect | `app/Http/Controllers/API/APP/DeviceController.php` |
| App resource | `app/Http/Resources/API/UserDeviceResource.php` — no snapshot field |
| Admin index | `app/Http/Controllers/Admin/UserDeviceController.php::index` — `select` without `longText`, `has_connect_snapshot` via `selectRaw` |
| Admin read | `UserDeviceController::connectSnapshot` → `response()->success([...])` |
| Admin resource | `app/Http/Resources/UserDeviceResource.php` — `has_connect_snapshot` only |
| Route | `routes/web.php` — `admin.devices.connect-snapshot.show` |
| Admin page | `resources/js/Pages/Admin/UserDevice/Index.vue` |
| Modal | `resources/js/Modals/DeviceConnectSnapshotModal.vue` |
| Dev tooling | `app/Console/Commands/GenerateTestDataCommand.php` |

### Connect behavior (implemented)

- Removed early return when `connected_at` and same `android_id` — every connect runs `DeviceService::update`.
- Token bound to **different** `android_id` still returns fail message (unchanged).
- Snapshot key included in `update()` only when argument is non-null and non-empty.

## Phase 1 — Database and Model ✅

Migration adds nullable `longText` `device_connect_snapshot` on `user_devices`. Model `$fillable` and `@property` updated. No array cast (verbatim string).

## Phase 2 — App API Connect ✅

Validation:

```php
'device_connect_snapshot' => ['sometimes', 'nullable', 'string', 'max:1048576'],
```

Normalization: missing / `null` / `''` → `null` passed to service (DB snapshot preserved).

`DeviceService::update(..., ?string $device_connect_snapshot = null)` — snapshot in `$attributes` only when non-empty.

## Phase 3 — Admin Lazy Read API ✅

- `GET /admin/devices/{device}/connect-snapshot`
- Name: `admin.devices.connect-snapshot.show`
- Payload: `device_id`, `has_snapshot`, `device_connect_snapshot`, `updated_at` (ISO)

## Phase 4 — Admin UI ✅

- Table column «Снимок», button «Просмотр» disabled when `!has_connect_snapshot`
- Mobile: «Снимок устройства» in card footer
- Modal loads on open via axios + Ziggy route; copy uses raw string from API

## Phase 5 — Verification Checklist

### API

- [x] Connect without `device_connect_snapshot` → 200, column unchanged (legacy APK)
- [x] Connect with valid JSON string &lt; 1 MiB → column updated, not in response body
- [x] Second connect same `android_id` without snapshot → structured fields / `connected_at` updated, snapshot unchanged
- [x] Second connect same `android_id` with snapshot → snapshot overwritten
- [x] Connect with different `android_id` on used token → still rejected
- [x] Payload &gt; 1 MiB → 422 (`max:1048576`)
- [x] Ping does not touch snapshot

### Admin

- [x] Index payload has no large snapshot strings
- [x] Modal loads via `admin.devices.connect-snapshot.show`
- [x] Valid JSON renders indented
- [x] Invalid JSON renders as plaintext
- [x] Copy uses raw stored string
- [x] Trader `/trader/devices` — no snapshot UI (only `has_connect_snapshot` boolean in shared resource, no modal)

### Commands

- [x] `vendor/bin/pint --dirty` on touched PHP
- [x] `php artisan optimize` + ziggy after web route

Manual QA on staging/production still recommended after deploy (run migration).

## Phase 6 — Tests (Deferred)

Suggested coverage when requested:

- Feature: connect persists snapshot
- Feature: reconnect overwrites / preserves per empty field
- Feature: admin endpoint 403 for trader, 200 for admin
- Feature: oversize snapshot 422

## Deployment Note

Run migration `2026_05_22_212222_add_device_connect_snapshot_to_user_devices_table` on each environment before relying on admin snapshot UI.

## See Also

- Trader device pings (lazy-load pattern reference): `UserDevicePingController`, `trader.devices.pings`
