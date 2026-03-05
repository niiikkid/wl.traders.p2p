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
        // Перенос данных из старого типа отношений в новый
        $paymentDetails = DB::table('payment_details')->whereNotNull('payment_gateway_id')->get();

        foreach ($paymentDetails as $paymentDetail) {
            // Добавляем запись в связующую таблицу для основного payment_gateway
            if ($paymentDetail->payment_gateway_id) {
                DB::table('payment_detail_payment_gateway')->insert([
                    'payment_detail_id' => $paymentDetail->id,
                    'payment_gateway_id' => $paymentDetail->payment_gateway_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //no way back
    }
};
