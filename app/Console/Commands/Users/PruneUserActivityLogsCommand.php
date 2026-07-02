<?php

declare(strict_types=1);

namespace App\Console\Commands\Users;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneUserActivityLogsCommand extends Command
{
    protected $signature = 'users:activity-logs:prune';

    protected $aliases = ['app:prune-user-activity-logs'];

    protected $description = 'Удаляет логи действий пользователей старше 3 месяцев';

    public function handle(): int
    {
        $threshold = now()->subMonths(3);
        $totalDeleted = 0;

        do {
            $deleted = DB::table('user_activity_logs')
                ->where('created_at', '<', $threshold)
                ->limit(5000)
                ->delete();

            $totalDeleted += $deleted;
        } while ($deleted > 0);

        $this->info("Удалено логов действий: {$totalDeleted}");

        return self::SUCCESS;
    }
}
