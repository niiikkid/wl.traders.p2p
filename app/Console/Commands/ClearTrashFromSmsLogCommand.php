<?php

namespace App\Console\Commands;

use App\Models\SmsLog;
use Illuminate\Console\Command;

class ClearTrashFromSmsLogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-trash-from-sms-log-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

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
            ->whereDate('created_at', '<', now()->subDays(3))
            ->delete();
    }
}
