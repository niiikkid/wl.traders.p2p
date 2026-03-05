<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('payout_offers');
        Schema::dropIfExists('payout_gateways');

        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'is_payout_online',
                'payouts_enabled',
                'payout_hold_enabled',
                'payout_hold_minutes',
                'payout_max_active_payouts',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('payment_gateways', function (Blueprint $table) {
            foreach ([
                'payouts_enabled',
                'trader_commission_rate_for_payouts',
                'total_service_commission_rate_for_payouts',
                'reservation_time_for_payouts',
                'payout_reservation_time',
                'sell_price_markup_rate',
                'payout_service_commission_rate',
            ] as $column) {
                if (Schema::hasColumn('payment_gateways', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::create('payout_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('name')->nullable();
            $table->string('domain')->nullable();
            $table->string('callback_url')->nullable();
            $table->boolean('enabled')->nullable();
            $table->foreignId('owner_id')->nullable();
            $table->timestamps();
        });

        Schema::create('payout_offers', function (Blueprint $table) {
            $table->id();
            $table->string('max_amount')->nullable();
            $table->string('min_amount')->nullable();
            $table->string('currency')->nullable();
            $table->longText('detail_types')->nullable();
            $table->boolean('active')->nullable();
            $table->boolean('occupied')->default(false);
            $table->foreignId('payment_gateway_id')->nullable();
            $table->foreignId('owner_id')->nullable();
            $table->timestamps();
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('external_id')->nullable();
            $table->string('detail')->nullable();
            $table->string('detail_type')->nullable();
            $table->string('requisite_type')->nullable();
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
            $table->longText('refuse_reason')->nullable();
            $table->longText('cancel_reason')->nullable();
            $table->foreignId('previous_trader_id')->nullable();
            $table->string('video_receipt')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_payout_online')) {
                $table->boolean('is_payout_online')->default(false)->after('is_online');
            }
            if (! Schema::hasColumn('users', 'payouts_enabled')) {
                $table->boolean('payouts_enabled')->default(false)->after('is_payout_online');
            }
            if (! Schema::hasColumn('users', 'payout_hold_enabled')) {
                $table->boolean('payout_hold_enabled')->default(true)->after('payouts_enabled');
            }
            if (! Schema::hasColumn('users', 'payout_hold_minutes')) {
                $table->unsignedInteger('payout_hold_minutes')->default(60)->after('payout_hold_enabled');
            }
            if (! Schema::hasColumn('users', 'payout_max_active_payouts')) {
                $table->unsignedInteger('payout_max_active_payouts')->default(1)->after('payout_hold_minutes');
            }
        });

        Schema::table('payment_gateways', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_gateways', 'payouts_enabled')) {
                $table->boolean('payouts_enabled')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('payment_gateways', 'trader_commission_rate_for_payouts')) {
                $table->float('trader_commission_rate_for_payouts')->default(0);
            }
            if (! Schema::hasColumn('payment_gateways', 'total_service_commission_rate_for_payouts')) {
                $table->float('total_service_commission_rate_for_payouts')->default(0);
            }
            if (! Schema::hasColumn('payment_gateways', 'reservation_time_for_payouts')) {
                $table->unsignedInteger('reservation_time_for_payouts')->default(10);
            }
        });
    }
};



