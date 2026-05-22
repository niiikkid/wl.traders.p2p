# Shadow SMS Log — Implementation Status

> Source: Local implementation summary from Cursor session
> Collected: 2026-05-23
> Published: Unknown

## Status

The shadow SMS log feature has been implemented in code.

## Implemented Backend Scope

- New table migration: `database/migrations/2026_05_23_013100_create_shadow_sms_logs_table.php`.
- New model/entity: `App\Models\ShadowSmsLog`.
- New reason enum: `App\Enums\ShadowSmsLogFilterReason`.
- New DTO: `App\DTO\SMS\ShadowSmsLogData`.
- New async job: `App\Jobs\RecordShadowSmsLogJob`, dispatched to existing `sms` queue.
- New service: `App\Services\Sms\ShadowSmsLogService`.
- New admin controller: `App\Http\Controllers\Admin\ShadowSmsLogController`.
- New resource: `App\Http\Resources\ShadowSmsLogResource`.
- `SmsController::store()` now records shadow log jobs for:
  - max message length (`max_message_length`);
  - sender stop list (`sender_stop_list`);
  - stop word (`stop_word`).
- `Parser` now exposes `findMatchedStopWord()` while preserving `hasStopWord()` behavior.
- `SettingsService` and `SettingsServiceContract` now support `shadow_sms_log_enabled`.
- `app:install-settings` was run to install the new setting with default enabled.

## Implemented Admin UI Scope

- New page: `resources/js/Pages/Admin/ShadowSmsLog/Index.vue`.
- New shared automation navigation component: `resources/js/Components/Automation/AutomationNavButtons.vue`.
- Existing automation pages now use the shared navigation:
  - messages;
  - shadow log;
  - application;
  - devices.
- Admin menu highlights `admin.shadow-sms-logs.*` as part of «Автоматика».
- Shadow log page includes:
  - pagination;
  - filters by login, device name, sender, message;
  - global enable/disable toggle;
  - hard delete all button with confirmation modal.

## Routes

- `GET admin/shadow-sms-logs` → `admin.shadow-sms-logs.index`
- `PATCH admin/shadow-sms-logs/enabled` → `admin.shadow-sms-logs.enabled.update`
- `DELETE admin/shadow-sms-logs` → `admin.shadow-sms-logs.destroy-all`

## Verification Performed

- `vendor/bin/pint --dirty --format agent`
- `php artisan app:install-settings --no-interaction`
- `php artisan optimize --no-interaction`
- `php artisan ziggy:generate resources/js/ziggy-routes.js --no-interaction`
- PHP syntax checks (`php -l`) on new and modified PHP files
- `php artisan route:list --name=shadow-sms-logs --except-vendor`

## Not Performed

- Automated test suite was not run per project rule requiring explicit user request.
- Database migration was not run to avoid applying pending migrations automatically.
