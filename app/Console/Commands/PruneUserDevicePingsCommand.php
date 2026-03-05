<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneUserDevicePingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-user-device-pings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Удаляет записи пингов устройств старше 65 минут';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $threshold = now()->subMinutes(65);

        $totalDeleted = 0;

        // Удаляем батчами, чтобы не держать долгую блокировку таблицы
        do {
            $deleted = DB::table('user_device_pings')
                ->where('created_at', '<', $threshold)
                ->limit(5000)
                ->delete();

            $totalDeleted += $deleted;
        } while ($deleted > 0);

        $this->info("Удалено записей: {$totalDeleted}");

        return self::SUCCESS;
    }
}


