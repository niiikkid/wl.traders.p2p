<?php

namespace App\Console\Commands;

use App\Models\UserLoginHistory;
use Illuminate\Console\Command;

class DeleteLoginHistoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-login-history';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        UserLoginHistory::query()->delete();
    }
}
