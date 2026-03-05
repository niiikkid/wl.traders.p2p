<?php

use App\Models\Merchant;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \App\Models\PaymentGateway::query()
            ->update([
                'buy_price_markup_rate' => 7,
                'order_service_commission_rate' => 10,
            ]);

        $paymentGateways = \App\Models\PaymentGateway::all();
        $merchants = Merchant::all();

        foreach ($merchants as $merchant) {
            $gatewaySettings = [];

            $paymentGateways->each(function ($paymentGateway) use (&$gatewaySettings) {
                if (! isset($gatewaySettings[$paymentGateway->id]['merchant_commission'])) {
                    $gatewaySettings[$paymentGateway->id]['merchant_commission'] = (float)$paymentGateway->order_service_commission_rate;
                }
                if (! isset($gatewaySettings[$paymentGateway->id]['active'])) {
                    $gatewaySettings[$paymentGateway->id]['active'] = true;
                }
            });

            foreach ($gatewaySettings as $key => $value) {
                $gatewaySettings[$key]['merchant_commission'] = (float)$gatewaySettings[$key]['merchant_commission'];
            }

            $merchant->update(['gateway_settings' => $gatewaySettings]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
