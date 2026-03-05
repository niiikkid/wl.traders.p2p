<?php

use App\Models\Dispute;
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

        Schema::table('disputes', function (Blueprint $table) {
            $table->foreignId('trader_id')->nullable()->after('order_id');
        });

        Dispute::query()
            ->with('order.paymentDetail', function ($query) {
                $query->select('id', 'user_id');
            })
            ->select('id', 'order_id')
            ->each(function (Dispute $dispute) {
                $dispute->update([
                    'trader_id' => $dispute->order->paymentDetail->user_id,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn('trader_id');
        });
    }
};
