<?php

namespace App\Console\Commands;

use App\Models\PaymentDetail;
use Illuminate\Console\Command;

class ResetPaymentDetailLimitsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-payment-detail-limits';

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
        PaymentDetail::query()
            ->update([
                'current_daily_limit' => 0,
                'current_daily_successful_orders_count' => 0,
            ]);
    }
}
