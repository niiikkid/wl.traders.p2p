# Device Connect Snapshot — Implementation (Shipped)

> Source: Codebase implementation session (Cursor)
> Collected: 2026-05-23
> Published: Unknown

## Summary

Feature shipped: optional `device_connect_snapshot` on app device connect, `longText` storage, admin lazy read + modal UI.

## Database

- Migration: `database/migrations/2026_05_22_212222_add_device_connect_snapshot_to_user_devices_table.php`
- Column: `user_devices.device_connect_snapshot` (`longText`, nullable)

## API (app)

- `POST /api/app/device/connect` — validation `sometimes|nullable|string|max:1048576`
- Empty/missing snapshot: connect succeeds; DB snapshot unchanged
- Non-empty snapshot: overwritten on each connect (early-return on same `android_id` removed)
- `app/Http/Resources/API/UserDeviceResource` — snapshot not exposed

## API (admin)

- `GET /admin/devices/{device}/connect-snapshot` — route `admin.devices.connect-snapshot.show`
- Response via `response()->success([...])` with `has_snapshot`, `device_connect_snapshot`, `updated_at`

## Admin UI

- `resources/js/Pages/Admin/UserDevice/Index.vue` — column «Снимок», button «Просмотр»
- `resources/js/Modals/DeviceConnectSnapshotModal.vue` — lazy load, pretty JSON, copy raw string
- Index query excludes `longText`; exposes `has_connect_snapshot` via `selectRaw`

## Other

- `app/Console/Commands/GenerateTestDataCommand.php` — test connect payload includes snapshot JSON
- Post-route: `php artisan optimize`, `php artisan ziggy:generate`

## Not done

- Automated PHPUnit coverage (deferred until requested)
