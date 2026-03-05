<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;

class CloseManuallyOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:close-manually-orders';

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
        Order::query()
            ->withoutGlobalScopes()
            ->whereNull('expires_at')
            ->whereNull('payment_detail_id')
            ->where('status', OrderStatus::PENDING)
            ->whereDate('created_at', '<', now()->subHours(6))
            ->update([
                'status' => OrderStatus::FAIL,
                'finished_at' => now(),
            ]);
    }
}
