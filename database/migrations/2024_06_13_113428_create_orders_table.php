<?php

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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('external_id')->nullable();
            $table->string('base_amount')->nullable(); //стартовая сумма
            $table->string('amount')->nullable(); //стартовая сумма с комиссией сервиса (эту сумму платит клиент)
            $table->string('profit')->nullable(); //pay_amount конвертированная в usdt
            $table->string('trader_profit')->nullable(); //profit * trader_commission_rate, процент трейдера с конвертации
            $table->string('merchant_profit')->nullable(); // profit - service_profit
            $table->string('service_profit')->nullable(); // profit * service_commission_rate_total
            $table->string('currency')->nullable(); //фиат (например: рубли)
            $table->string('base_conversion_price')->nullable(); // базовая цена конвертации в usdt без комиссии
            $table->string('conversion_price')->nullable(); // базовая цена конвертации в usdt * trader_commission_rate
            $table->float('trader_commission_rate', 2)->nullable(); // % комиссии трейдера, накладывается на базовую цену конвертации
            $table->float('service_commission_rate_total', 2)->nullable(); // % комиссии сервиса, накладывается на сумму
            $table->float('service_commission_rate_merchant', 2)->nullable(); // часть комиссии сервиса которую платит мерчант
            $table->float('service_commission_rate_client', 2)->nullable(); // часать комиссии сервиса которую платит клиент
            $table->string('status')->nullable();
            $table->longText('callback_url')->nullable();
            $table->longText('success_url')->nullable();
            $table->longText('fail_url')->nullable();
            $table->foreignId('payment_gateway_id')->nullable();
            $table->foreignId('payment_detail_id')->nullable();
            $table->foreignId('merchant_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
