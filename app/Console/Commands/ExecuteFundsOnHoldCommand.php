<?php

namespace App\Console\Commands;

use App\Enums\FundsOnHoldStatus;
use App\Models\FundsOnHold;
use App\Utils\Transaction;
use Illuminate\Console\Command;

class ExecuteFundsOnHoldCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:execute-funds-on-hold';

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
        FundsOnHold::query()
            ->where('status', FundsOnHoldStatus::PENDING_FOR_EXECUTION)
            ->whereDate('hold_until', '<', now())
            ->get()
            ->each(function (FundsOnHold $fundsOnHold) {
                Transaction::run(function () use ($fundsOnHold) {
                    services()->fundsHolder()->execute($fundsOnHold);
                });
            });
    }
}
