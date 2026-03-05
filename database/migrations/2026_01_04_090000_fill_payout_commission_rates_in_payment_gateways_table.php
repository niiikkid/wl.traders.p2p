<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Заполняем значения по умолчанию для существующих записей
        DB::table('payment_gateways')
            ->where(function ($query) {
                $query->whereNull('trader_commission_rate_for_payouts')
                    ->orWhere('trader_commission_rate_for_payouts', 0);
            })
            ->update([
                'trader_commission_rate_for_payouts' => 2,
            ]);

        DB::table('payment_gateways')
            ->where(function ($query) {
                $query->whereNull('total_service_commission_rate_for_payouts')
                    ->orWhere('total_service_commission_rate_for_payouts', 0);
            })
            ->update([
                'total_service_commission_rate_for_payouts' => 3,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Откатываем только те записи, которые мы явно установили
        DB::table('payment_gateways')
            ->where('trader_commission_rate_for_payouts', 2)
            ->update([
                'trader_commission_rate_for_payouts' => 0,
            ]);

        DB::table('payment_gateways')
            ->where('total_service_commission_rate_for_payouts', 3)
            ->update([
                'total_service_commission_rate_for_payouts' => 0,
            ]);
    }
};


