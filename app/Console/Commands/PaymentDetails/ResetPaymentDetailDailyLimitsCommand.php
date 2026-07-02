<?php

namespace App\Console\Commands\PaymentDetails;

use App\Services\PaymentDetail\PaymentDetailLimitResetService;
use Illuminate\Console\Command;

class ResetPaymentDetailDailyLimitsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment-details:limits:reset-daily';

    protected $aliases = ['app:reset-payment-detail-daily-limits'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset daily payment detail limits.';

    /**
     * Execute the console command.
     */
    public function handle(PaymentDetailLimitResetService $paymentDetailLimitResetService): void
    {
        $paymentDetailLimitResetService->resetDailyLimitsForAll();
    }
}
