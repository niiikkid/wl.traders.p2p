<?php

namespace App\Console\Commands\Maintenance;

use App\Models\SmsLog;
use Illuminate\Console\Command;

class ClearTrashFromSmsLogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maintenance:sms-logs:prune-orphans';

    protected $aliases = ['app:clear-trash-from-sms-log-command'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Production only: delete orphan SMS logs older than one month.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (! is_production()) {
            return;
        }

        SmsLog::query()
            ->whereNull('order_id')
            ->whereDate('created_at', '<', now()->subMonth())
            ->delete();
    }
}
