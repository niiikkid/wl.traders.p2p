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
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();

            $table->string('uuid')->unique();

            $table->foreignId('merchant_id');
            $table->foreignId('trader_id')->nullable();
            $table->foreignId('payment_gateway_id');

            $table->string('payout_method_type'); // sbp|card
            $table->string('requisites');
            $table->string('initials')->nullable();

            // Фиат (например RUB)
            $table->string('amount_fiat');
            $table->string('amount_fiat_currency')->default('rub');

            // Курс (покупка USDT) и источник фиксации
            $table->string('rate_market')->default('bybit');
            $table->string('conversion_price')->nullable(); // цена 1 USDT в фиате
            $table->string('conversion_price_currency')->default('rub');
            $table->timestamp('rate_fixed_at')->nullable();

            // Промежуточные значения в USDT
            $table->string('usdt_body')->nullable();
            $table->string('usdt_body_currency')->default('usdt');

            $table->string('total_fee')->nullable();
            $table->string('total_fee_currency')->default('usdt');

            $table->string('trader_fee')->nullable();
            $table->string('trader_fee_currency')->default('usdt');

            $table->string('teamlead_fee')->nullable();
            $table->string('teamlead_fee_currency')->default('usdt');

            $table->string('service_fee')->nullable();
            $table->string('service_fee_currency')->default('usdt');

            // Движение средств (в USDT)
            $table->string('merchant_debit')->nullable();
            $table->string('merchant_debit_currency')->default('usdt');

            $table->string('trader_credit')->nullable();
            $table->string('trader_credit_currency')->default('usdt');

            // Ставки комиссий (проценты)
            $table->float('total_commission_rate', 8, 2)->nullable();
            $table->float('trader_commission_rate', 8, 2)->nullable();
            $table->float('teamlead_commission_rate', 8, 2)->nullable();
            $table->float('service_commission_rate', 8, 2)->nullable();

            $table->string('status')->default('open');

            $table->timestamp('taken_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('hold_until')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            $table->json('calc_meta')->nullable();

            $table->timestamps();

            $table->index(['status', 'payment_gateway_id']);
            $table->index(['status', 'payout_method_type']);
            $table->index(['merchant_id', 'status']);
            $table->index(['trader_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};


