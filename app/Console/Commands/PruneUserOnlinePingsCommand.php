<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneUserOnlinePingsCommand extends Command
{
    protected $signature = 'app:prune-user-online-pings';

    protected $description = 'Удаляет онлайн-пинги веб-панели старше 7 дней';

    public function handle(): int
    {
        $threshold = now()->subDays(7);
        $totalDeleted = 0;

        // Удаляем батчами, чтобы не держать долгую блокировку таблицы
        do {
            $deleted = DB::table('user_online_pings')
                ->where('created_at', '<', $threshold)
                ->limit(5000)
                ->delete();

            $totalDeleted += $deleted;
        } while ($deleted > 0);

        $this->info("Удалено онлайн-пингов: {$totalDeleted}");

        return self::SUCCESS;
    }
}
