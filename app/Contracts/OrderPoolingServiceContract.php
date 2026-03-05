<?php

namespace App\Contracts;

use App\Http\Requests\API\H2H\Order\StoreRequest;
use App\Http\Requests\API\Merchant\Order\StoreRequest as MerchantStoreRequest;
use Illuminate\Http\JsonResponse;

interface OrderPoolingServiceContract
{
    /**
     * Обрабатывает запрос на создание сделки через OrderPooling
     */
    public function processOrderPooling(StoreRequest|MerchantStoreRequest $request): JsonResponse;
}
