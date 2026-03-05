<?php

namespace App\Contracts;

use App\Models\Merchant;
use App\Models\MerchantApiRequestLog;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Throwable;

interface MerchantApiLogServiceContract
{
    /**
     * Логирует запрос от мерчанта на создание сделки
     *
     * @param Request $request Объект запроса
     * @param Merchant $merchant Объект мерчанта
     * @param array $requestData Данные запроса
     * @return string Уникальный идентификатор запроса
     */
    public function logRequest(Request $request, Merchant $merchant, array $requestData): string;

    /**
     * Обновляет лог после получения ответа
     *
     * @param Merchant $merchant
     * @param string $externalID
     * @param string $requestID Уникальный идентификатор запроса
     * @param JsonResponse $response Объект ответа
     * @param Order|null $order Созданный заказ (если успешно)
     * @param Throwable|null $exception Исключение, если оно возникло
     */
    public function updateWithResponse(Merchant $merchant, string $externalID, string $requestID, JsonResponse $response, ?Order $order = null, ?string $exceptionClass = null, ?string $exceptionMessage = null): void;
}
