<?php

namespace App\Contracts;

use App\Models\Merchant;
use App\Models\Order;
use App\Models\Payout\Payout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface MerchantApiLogServiceContract
{
    /**
     * Logs a merchant API request.
     *
     * @param  Request  $request  Request instance
     * @param  Merchant  $merchant  Merchant instance
     * @param  array  $requestData  Request payload
     * @param  string  $requestType  API request type
     * @return string Unique request identifier
     */
    public function logRequest(Request $request, Merchant $merchant, array $requestData, string $requestType = 'order'): string;

    /**
     * Updates the log after building a response.
     *
     * @param  string  $requestID  Unique request identifier
     * @param  JsonResponse  $response  Response instance
     * @param  Order|null  $order  Created order, if any
     * @param  string|null  $exceptionClass  Exception class, if any
     * @param  string|null  $exceptionMessage  Exception message, if any
     * @param  Payout|null  $payout  Created or affected payout, if any
     */
    public function updateWithResponse(Merchant $merchant, string $externalID, string $requestID, JsonResponse $response, ?Order $order = null, ?string $exceptionClass = null, ?string $exceptionMessage = null, ?Payout $payout = null): void;
}
