<?php

namespace App\Console\Commands;

use App\Services\PaymentDetail\PaymentDetailLimitResetService;
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
    public function handle(PaymentDetailLimitResetService $paymentDetailLimitResetService): void
    {
        $paymentDetailLimitResetService->resetDailyLimitsForAll();
        $paymentDetailLimitResetService->resetMonthlyLimitsDueToday();
    }
}
