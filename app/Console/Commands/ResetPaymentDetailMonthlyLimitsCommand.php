<?php

namespace App\Console\Commands;

use App\Services\PaymentDetail\PaymentDetailLimitResetService;
use Illuminate\Console\Command;

class ResetPaymentDetailMonthlyLimitsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-payment-detail-monthly-limits';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset monthly payment detail limits due today.';

    /**
     * Execute the console command.
     */
    public function handle(PaymentDetailLimitResetService $paymentDetailLimitResetService): void
    {
        $paymentDetailLimitResetService->resetMonthlyLimitsDueTodayIfNeeded();
    }
}
