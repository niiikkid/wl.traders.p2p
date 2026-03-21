<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneUserOnlinePeriodsCommand extends Command
{
    protected $signature = 'app:prune-user-online-periods';

    protected $description = 'Удаляет интервалы онлайн старше 180 дней';

    public function handle(): int
    {
        $threshold = now()->subDays(180);
        $totalDeleted = 0;

        do {
            $deleted = DB::table('user_online_periods')
                ->where('ended_at', '<', $threshold)
                ->limit(5000)
                ->delete();

            $totalDeleted += $deleted;
        } while ($deleted > 0);

        $this->info("Удалено интервалов онлайн: {$totalDeleted}");

        return self::SUCCESS;
    }
}
