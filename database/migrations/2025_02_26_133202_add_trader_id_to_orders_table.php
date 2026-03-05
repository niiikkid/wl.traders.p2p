<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        ini_set('memory_limit', '1G');

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('trader_id')->nullable()->after('payment_detail_id');
        });

        Order::query()
            ->with('paymentDetail:user_id,id')
            ->select('id', 'payment_detail_id')
            ->chunkById(1000, function ($orders) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($orders) {
                    foreach ($orders as $order) {
                        $order->update([
                            'trader_id' => $order->paymentDetail->user_id,
                        ]);
                    }
                });
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('trader_id');
        });
    }
};
