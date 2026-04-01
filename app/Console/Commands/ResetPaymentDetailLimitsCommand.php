<?php

namespace App\Console\Commands;

use App\Models\PaymentDetail;
use Carbon\Carbon;
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
        $today = Carbon::today();

        PaymentDetail::query()
            ->update([
                'current_daily_limit' => 0,
                'current_daily_successful_orders_count' => 0,
            ]);

        PaymentDetail::query()
            ->whereNotNull('monthly_limit_reset_day')
            ->where(function ($query) use ($today) {
                $query->where('monthly_limit_reset_day', $today->day);

                if ($today->isLastOfMonth()) {
                    $query->orWhere('monthly_limit_reset_day', '>', $today->daysInMonth);
                }
            })
            ->update([
                'current_monthly_limit' => 0,
            ]);
    }
}
