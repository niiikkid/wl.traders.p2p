<?php

namespace App\Console\Commands;

use App\Models\SmsLog;
use App\Models\UserDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateSmsLogsToLegacyDevice extends Command
{
    protected $signature = 'sms-logs:migrate-to-legacy-device';
    protected $description = 'Связывает SMS логи с существующими Legacy устройствами';

    public function handle()
    {
        $this->info('Начинаем связывание SMS логов с Legacy устройствами...');

        // Получаем все Legacy устройства одним запросом
        $legacyDevices = UserDevice::where('android_id', 'like', 'legacy_%')
            ->get(['id', 'user_id'])
            ->keyBy('user_id');

        $this->info("Найдено Legacy устройств: " . $legacyDevices->count());

        // Обновляем логи пакетами
        $processed = 0;
        $batchSize = 1000;

        do {
            $count = DB::update("
                UPDATE sms_logs sl
                INNER JOIN (
                    SELECT sl2.id, ud.id as device_id
                    FROM sms_logs sl2
                    INNER JOIN user_devices ud ON ud.user_id = sl2.user_id
                    WHERE sl2.user_device_id IS NULL
                    AND ud.android_id LIKE 'legacy_%'
                    LIMIT ?
                ) updates ON updates.id = sl.id
                SET sl.user_device_id = updates.device_id
            ", [$batchSize]);

            $processed += $count;
            $this->info("Обработано SMS логов: {$processed}");

        } while ($count > 0);

        $this->info('Миграция завершена успешно! Всего обработано: ' . $processed);
    }
}
