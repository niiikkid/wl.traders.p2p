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
            $table->string('uuid')->nullable();
            $table->string('external_id')->nullable();

            $table->string('detail')->nullable();
            $table->string('detail_type')->nullable();
            $table->string('detail_initials')->nullable();

            $table->string('payout_amount')->nullable();
            $table->string('currency')->nullable();
            $table->string('base_liquidity_amount')->nullable();
            $table->string('liquidity_amount')->nullable();

            $table->float('service_commission_rate', 2)->nullable();
            $table->string('service_commission_amount')->nullable();
            $table->string('trader_profit_amount')->nullable();

            $table->float('trader_exchange_markup_rate', 2)->nullable();
            $table->string('trader_exchange_markup_amount')->nullable();

            $table->string('base_exchange_price')->nullable();
            $table->string('exchange_price')->nullable();

            $table->string('status')->nullable();
            $table->string('sub_status')->nullable();

            $table->longText('callback_url')->nullable();

            $table->foreignId('payout_offer_id')->nullable();
            $table->foreignId('payout_gateway_id')->nullable();
            $table->foreignId('payment_gateway_id')->nullable();
            $table->foreignId('sub_payment_gateway_id')->nullable();
            $table->foreignId('trader_id')->nullable();
            $table->foreignId('owner_id')->nullable();

            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
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
