<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\PaymentGatewayResource;

class PaymentGatewayController extends Controller
{
    public function index()
    {
        $paymentGateways = queries()->paymentGateway()->getAllActive();

        $paymentGateways = PaymentGatewayResource::collection($paymentGateways);

        return response()->success($paymentGateways);
    }
}
