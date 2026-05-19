<?php

namespace App\Services\Logging;

use App\Contracts\MerchantApiLogServiceContract;
use App\Exceptions\OrderException;
use App\Jobs\CreateMerchantApiLogJob;
use App\Jobs\UpdateMerchantApiLogJob;
use App\Models\Merchant;
use App\Models\MerchantApiRequestLog;
use App\Models\Order;
use App\Models\Payout\Payout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MerchantApiLogService implements MerchantApiLogServiceContract
{
    /**
     * Хранение времени начала запроса для каждого request_id
     *
     * @var array<string, float>
     */
    private array $requestStartTime = [];

    /**
     * Logs a merchant API request.
     *
     * @param  Request  $request  Request instance
     * @param  Merchant  $merchant  Merchant instance
     * @param  array  $requestData  Request payload
     * @param  string  $requestType  API request type
     * @return string Unique request identifier
     */
    public function logRequest(Request $request, Merchant $merchant, array $requestData, string $requestType = MerchantApiRequestLog::TYPE_ORDER): string
    {
        $requestType = in_array($requestType, [MerchantApiRequestLog::TYPE_ORDER, MerchantApiRequestLog::TYPE_PAYOUT], true)
            ? $requestType
            : MerchantApiRequestLog::TYPE_ORDER;

        // Генерируем уникальный идентификатор запроса
        $requestId = (string) Str::uuid();

        // Запоминаем время начала запроса для последующего расчета времени выполнения
        $this->requestStartTime[$requestId] = microtime(true);

        // Создаем лог-запись асинхронно
        CreateMerchantApiLogJob::dispatch(
            $merchant,
            $requestData,
            $requestId,
            $request->ip(),
            $request->userAgent(),
            $requestType,
        );

        return $requestId;
    }

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
    public function updateWithResponse(Merchant $merchant, string $externalID, string $requestID, JsonResponse $response, ?Order $order = null, ?string $exceptionClass = null, ?string $exceptionMessage = null, ?Payout $payout = null): void
    {
        $responseData = json_decode($response->getContent(), true);
        $isSuccessful = $response->getStatusCode() === 200 && ($responseData['success'] ?? '') === true;

        $errorMessage = $isSuccessful ? null : ($responseData['message'] ?? 'Неизвестная ошибка');

        // Если есть исключение и оно не является OrderException, записываем информацию о нем
        if (is_a($exceptionClass, OrderException::class, true)) {
            $exceptionClass = null;
            $exceptionMessage = null;
        } elseif ($exceptionClass || $exceptionMessage) {
            Log::error('Unexpected merchant API request error', [
                'merchant_id' => $merchant->id,
                'merchant_uuid' => $merchant->uuid,
                'external_id' => $externalID,
                'request_id' => $requestID,
                'request_type' => $payout ? MerchantApiRequestLog::TYPE_PAYOUT : MerchantApiRequestLog::TYPE_ORDER,
                'exception_class' => $exceptionClass,
                'exception_message' => $exceptionMessage,
            ]);
        }

        // Рассчитываем время выполнения запроса в миллисекундах
        $executionTime = null;
        if (isset($this->requestStartTime[$requestID])) {
            $executionTime = (microtime(true) - $this->requestStartTime[$requestID]) * 1000;
            unset($this->requestStartTime[$requestID]); // Очищаем память
        }

        UpdateMerchantApiLogJob::dispatch(
            $merchant->id,
            $requestID,
            $responseData,
            $isSuccessful,
            $errorMessage,
            $order?->id,
            $exceptionClass,
            $exceptionMessage,
            $executionTime,
            $payout?->id,
        );
    }
}
