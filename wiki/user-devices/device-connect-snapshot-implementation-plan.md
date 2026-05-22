# Device Connect Snapshot — Implementation Plan

> Sources: User conversation, 2026-05-23; repository exploration, 2026-05-23
> Raw: [Device Connect Snapshot Requirements](../../raw/user-devices/2026-05-23-device-connect-snapshot-requirements.md)
> Updated: 2026-05-23

## Overview

Extend the automation app connect flow with an **optional** `device_connect_snapshot` on `POST /api/app/device/connect` (backward compatible: old APKs without the field keep working). When sent, the server stores it verbatim in `user_devices` (up to 1 MiB), never exposes it to the app API, and lets admins inspect it from the devices table via a lazy-loaded wide modal with pretty JSON, plaintext fallback, and raw copy.

## Product Decisions (Locked)

| Topic | Decision |
|-------|----------|
| API / DB field name | `device_connect_snapshot` (optional string, nullable in DB) |
| Backward compatibility | Omitted / `null` / empty → connect OK, snapshot column **not** updated |
| Storage | `longText`, nullable; store exactly as received when provided |
| Max size | 1 048 576 bytes when provided; if larger, skip persisting snapshot only |
| When written | Only when non-empty value sent; overwrite on each such connect (incl. same `android_id`) |
| Ping | No snapshot |
| App API response | Omit snapshot |
| Admin list | No snapshot in pagination payload |
| Admin read | Lazy `GET` JSON endpoint, admin middleware only |
| UI | Button in table row → wide modal, `useAppClipboard` for raw copy |
| Sensitivity | No masking |

## Existing Code Anchors

- Connect endpoint: `app/Http/Controllers/API/APP/DeviceController.php::connect`
- Routes: `routes/api.php` — `POST app/device/connect` under `device-access-token`
- Device update: `app/Services/Device/DeviceService.php::update`
- Contract: `app/Contracts/DeviceServiceContract.php`
- Model: `app/models/UserDevice.php`, migration `database/migrations/2025_03_03_151311_create_user_devices_table.php`
- App resource: `app/Http/Resources/API/UserDeviceResource.php` (must stay without snapshot)
- Admin list: `app/Http/Controllers/Admin/UserDeviceController.php`, `resources/js/Pages/Admin/UserDevice/Index.vue`
- Admin route: `routes/web.php` — `admin.devices.index` → `GET /admin/devices`
- Trader pings lazy pattern: `UserDevicePingController` + axios in `resources/js/Pages/UserDevice/Index.vue`
- Clipboard composable: `resources/js/composables/useAppClipboard.js`
- Wide modal reference: `resources/js/Pages/Admin/CascadeProviders/Index.vue` (`modal-box w-11/12 max-w-3xl`)

### Current Connect Quirk (Must Fix)

When `connected_at` is set and `android_id` matches the request, `connect` returns success **without** calling `DeviceService::update`. That blocks snapshot refresh and any future connect-time fields. Refactor so snapshot (and optionally `connected_at`) still update on every connect while preserving:

- rejection when token is bound to a **different** `android_id`;
- idempotent success response shape for the app (still `UserDeviceResource` without snapshot).

## Phase 1 — Database and Model

### Migration

Add column to `user_devices`:

```php
$table->longText('device_connect_snapshot')->nullable()
    ->comment('Raw JSON string from app device/connect');
```

Run migration in all environments.

### Model

- Add `device_connect_snapshot` to `$fillable` on `UserDevice`.
- Extend PHPDoc `@property string|null $device_connect_snapshot`.
- No cast to `array` — keep as string to preserve verbatim storage.

## Phase 2 — App API Connect

### Validation (`DeviceController::connect`)

```php
'device_connect_snapshot' => ['sometimes', 'nullable', 'string', 'max:1048576'],
```

Existing fields unchanged (`android_id`, `device_model`, `android_version`, `manufacturer`, `brand`).

Normalize before service call: treat missing, `null`, and `''` as “no snapshot in this request” (`null` argument).

### Size Handling (> 1 MiB)

When the field is present, `max:1048576` returns **422** for oversize bodies. Connect without the field never hits this rule.

If oversize should not fail connect (only skip write), use a custom rule instead of `max` and pass `null` to the service when `strlen > 1048576` while still updating other connect fields.

### `DeviceService::update` Signature

Extend contract and implementation:

```php
public function update(
    UserDevice $device,
    string $android_id,
    string $device_model,
    string $android_version,
    string $manufacturer,
    string $brand,
    ?string $device_connect_snapshot = null,
): UserDevice
```

Build the `update()` array with connect fields and `connected_at` => `now()`. Include `device_connect_snapshot` in the array **only when** the argument is non-null and non-empty; otherwise omit the key so the previous DB value is preserved.

### Controller Flow (Refactored)

```
validate
load device by Access-Token
if connected && android_id !== request.android_id → fail (unchanged)
call deviceService->update(..., device_connect_snapshot: $request->device_connect_snapshot)
return success(UserDeviceResource)  // no snapshot field
```

Remove the early return branch that skips `update` when `android_id` matches. Same-android reconnect must still run `update` (at least `connected_at` and structured fields); snapshot updates only when the client sends a non-empty value.

### API Resource

Confirm `app/Http/Resources/API/UserDeviceResource.php` does **not** expose `device_connect_snapshot`.

### Test / Dev Tooling

Update `app/Console/Commands/GenerateTestDataCommand.php` connect payload to include a minimal valid JSON string, e.g. `'device_connect_snapshot' => json_encode(['source' => 'test'], JSON_UNESCAPED_UNICODE)`.

## Phase 3 — Admin Lazy Read API

### Route

Inside existing admin route group (same middleware as `admin.devices.index`):

```
GET /admin/devices/{device}/connect-snapshot
name: admin.devices.connect-snapshot.show
```

After adding route:

- `php artisan optimize`
- `php artisan ziggy:generate resources/js/ziggy-routes.js`

### Controller Action

Add to `App\Http\Controllers\Admin\UserDeviceController` (or dedicated thin controller if preferred):

```php
public function connectSnapshot(UserDevice $device): JsonResponse
{
    return response()->json([
        'success' => true,
        'data' => [
            'device_id' => $device->id,
            'has_snapshot' => $device->device_connect_snapshot !== null
                && $device->device_connect_snapshot !== '',
            'device_connect_snapshot' => $device->device_connect_snapshot,
            'updated_at' => $device->updated_at?->toISOString(),
        ],
    ]);
}
```

Authorization: admin route group already restricts role; no trader route. Optional explicit `abort_unless(auth()->user()->hasRole('Super Admin'), 403)` only if the project uses finer-grained checks elsewhere — follow sibling admin controllers (devices index has no extra policy today).

### Admin `UserDeviceResource`

Do **not** add `device_connect_snapshot` to `app/Http/Resources/UserDeviceResource.php` used by the index. Optionally add a lightweight boolean for UX:

```php
'has_connect_snapshot' => filled($this->device_connect_snapshot),
```

Computed without loading heavy text if index uses `select()` — prefer subquery or `filled()` on model when column is not selected; simplest v1: always select column but omit from resource except boolean via `exists` query — **recommended v1**: add `has_connect_snapshot` only on index by selecting `id` + `device_connect_snapshot` is expensive. Better:

```php
// index query
->select([...existing columns..., DB::raw('device_connect_snapshot IS NOT NULL AND device_connect_snapshot != "" as has_connect_snapshot')])
```

Or keep UI simple: always show button; modal shows empty state if null.

## Phase 4 — Admin UI

### Table Column

In `resources/js/Pages/Admin/UserDevice/Index.vue`:

- Add column **«Снимок»** (or **«Данные устройства»**) as last column, right-aligned.
- Button: `btn btn-outline btn-xs` — label «Просмотр» / icon; disabled or hidden when `has_connect_snapshot === false` (optional).
- Mobile cards: same action in card footer.

### Modal Component

Create `resources/js/Modals/DeviceConnectSnapshotModal.vue` (or inline in Index if team prefers minimal files — modal component is cleaner).

Props: `open`, `deviceId`, `deviceName`.

On open:

```js
const { data } = await axios.get(route('admin.devices.connect-snapshot.show', { device: deviceId }))
```

State: `loading`, `error`, `raw` (string|null).

### Display Logic

```js
const displayContent = computed(() => {
  if (!raw.value) return { mode: 'empty', text: '' }
  try {
    const parsed = JSON.parse(raw.value)
    return { mode: 'json', text: JSON.stringify(parsed, null, 2) }
  } catch {
    return { mode: 'plaintext', text: raw.value }
  }
})
```

Render in `<pre class="text-xs overflow-auto max-h-[70vh] whitespace-pre-wrap break-words font-mono">`.

Modal shell (DaisyUI):

```html
<dialog :open="isOpen" class="modal">
  <div class="modal-box w-11/12 max-w-5xl">
    ...
    <button @click="copy(raw)">{{ copied ? 'Скопировано' : 'Скопировать' }}</button>
  </div>
  <form method="dialog" class="modal-backdrop"><button @click="close">close</button></form>
</dialog>
```

Copy uses `useAppClipboard` with **`raw`** (stored string), not pretty-printed text.

### Empty / Error States

- `has_snapshot: false` → «Снимок ещё не сохранён» (устройство не подключалось после релиза или payload был пуст).
- HTTP error → alert или inline `alert-error`.
- Loading → `loading loading-spinner`.

`script` above `template`; no scoped styles per project Vue rules.

## Phase 5 — Verification Checklist

### API

- [ ] Connect without `device_connect_snapshot` → 200, column unchanged (legacy APK)
- [ ] Connect with valid JSON string &lt; 1 MiB → column updated, not in response body
- [ ] Second connect same `android_id` without snapshot → structured fields/`connected_at` updated, snapshot unchanged
- [ ] Second connect same `android_id` with snapshot → snapshot overwritten
- [ ] Connect with different `android_id` on used token → still rejected
- [ ] Payload &gt; 1 MiB → 422 (if using validation max)
- [ ] Ping does not touch snapshot

### Admin

- [ ] Index payload has no large snapshot strings
- [ ] Modal loads via `admin.devices.connect-snapshot.show`
- [ ] Valid JSON renders indented
- [ ] Invalid JSON renders as plaintext
- [ ] Copy puts exact DB string in clipboard
- [ ] Trader `/trader/devices` has no snapshot UI

### Commands

- [ ] `vendor/bin/pint --dirty` on touched PHP
- [ ] `php artisan optimize` + ziggy after web route

## Phase 6 — Tests (When Requested)

Per project rules, add tests only when explicitly asked. Suggested coverage if enabled later:

- Feature: `POST /api/app/device/connect` persists snapshot
- Feature: reconnect overwrites snapshot
- Feature: admin snapshot endpoint 403 for trader, 200 for admin
- Feature: oversize snapshot 422

## Implementation Order Summary

1. Migration + model fillable
2. `DeviceServiceContract` + `DeviceService` + `DeviceController` refactor
3. `GenerateTestDataCommand` payload
4. Admin route + `connectSnapshot` action
5. `ziggy:generate` + `optimize`
6. `DeviceConnectSnapshotModal` + `Admin/UserDevice/Index.vue` column
7. Manual QA per checklist

## See Also

- Admin devices list: [wiki index](../index.md) — topic `user-devices` (this article)
- Trader device pings (lazy load pattern only): `UserDevicePingController`, `trader.devices.pings`
