<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Models\Order;
use App\Models\Payout\Payout;
use Illuminate\Console\Command;

class ClearOrderPayoutCallbackUrlsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:clear-order-payout-callback-urls';

    /**
     * @var string
     */
    protected $description = 'Только local: обнулить callback_url у мерчантов, сделок (orders) и выплат (payouts)';

    public function handle(): int
    {
        if (! is_local()) {
            $this->error('Команда разрешена только в окружении local (is_local).');

            return self::FAILURE;
        }

        $ordersUpdated = Order::withoutGlobalScopes()
            ->whereNotNull('callback_url')
            ->update(['callback_url' => null]);

        $payoutsUpdated = Payout::query()
            ->whereNotNull('callback_url')
            ->update(['callback_url' => null]);

        $merchantsUpdated = Merchant::query()
            ->where(function ($query) {
                $query->whereNotNull('callback_url')
                    ->orWhereNotNull('payout_callback_url');
            })
            ->update([
                'callback_url' => null,
                'payout_callback_url' => null,
            ]);

        $this->info("Merchants: обнулено callback_url и payout_callback_url — {$merchantsUpdated}");
        $this->info("Orders: обнулено callback_url — {$ordersUpdated}");
        $this->info("Payouts: обнулено callback_url — {$payoutsUpdated}");

        return self::SUCCESS;
    }
}
