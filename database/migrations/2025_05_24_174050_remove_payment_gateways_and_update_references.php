<?php

use App\Models\Order;
use App\Models\PaymentDetail;
use App\Models\PaymentGateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Константа для ID платежного метода, на который будут заменены удаляемые
        $defaultGatewayId = 1;
        $idThreshold = 164;

        // 1. Обновляем связи в промежуточной таблице payment_detail_payment_gateway
        DB::transaction(function () use ($defaultGatewayId, $idThreshold) {
            DB::table('payment_detail_payment_gateway')
                ->where('payment_gateway_id', '>=', $idThreshold)
                ->update(['payment_gateway_id' => $defaultGatewayId]);
        });

        // 2. Обновляем ссылки в таблице orders
        DB::transaction(function () use ($defaultGatewayId, $idThreshold) {
            Order::where('payment_gateway_id', '>=', $idThreshold)
                ->update(['payment_gateway_id' => $defaultGatewayId]);
        });

        // 3. Удаляем платежные методы с ID >= 164
        DB::transaction(function () use ($idThreshold) {
            PaymentGateway::where('id', '>=', $idThreshold)->delete();
        });
    }

    /**
     * Reverse the migrations.
     * Это необратимая миграция, поэтому метод down не выполняет никаких действий.
     */
    public function down(): void
    {
        // Невозможно восстановить удаленные данные
    }
};
