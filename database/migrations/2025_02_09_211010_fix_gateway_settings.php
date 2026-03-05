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
        \App\Models\Merchant::all()->each(function (\App\Models\Merchant $merchant) {
            $gatewaySettings = $merchant->gateway_settings;

            foreach ($gatewaySettings as $key => $gatewaySetting) {
                $item = [
                    'merchant_commission' => (float)$gatewaySetting['merchant_commission'],
                    'active' => true,
                ];
                unset($gatewaySettings[$key]);

                $gatewaySettings[$key] = $item;
            }

            $paymentGateways = \App\Models\PaymentGateway::all();

            $paymentGateways->each(function ($paymentGateway) use (&$gatewaySettings) {
                if (! isset($gatewaySettings[$paymentGateway->id])) {
                    $gatewaySettings[$paymentGateway->id] = [
                        'merchant_commission' => (float)$paymentGateway->order_service_commission_rate,
                        'active' => true,
                    ];
                }

                if ($gatewaySettings[$paymentGateway->id]['merchant_commission'] > $paymentGateway->order_service_commission_rate) {
                    $gatewaySettings[$paymentGateway->id]['merchant_commission'] = $paymentGateway->order_service_commission_rate;
                }
            });

            $merchant->update([
                'gateway_settings' => $gatewaySettings
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
