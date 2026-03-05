<?php

namespace App\Services\OrderPooling;

use App\Contracts\OrderPoolingServiceContract;
use App\Exceptions\AntiFraudException;
use App\Exceptions\OrderException;
use App\Http\Requests\API\H2H\Order\StoreRequest as H2HRequest;
use App\Http\Requests\API\Merchant\Order\StoreRequest as MerchantRequest;
use App\Http\Resources\API\H2H\OrderResource as H2HOrderResource;
use App\Http\Resources\API\Merchant\OrderResource as MOrderResource;
use App\Jobs\OrderPoolingJob;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Throwable;

class OrderPoolingService implements OrderPoolingServiceContract
{
    /**
     * Обрабатывает запрос на создание сделки через OrderPooling
     */
    public function processOrderPooling(H2HRequest|MerchantRequest $request): JsonResponse
    {
        $merchant = queries()->merchant()->findByUUID($request->merchant_id);

        // Логируем запрос и получаем request_id
        $requestId = services()->merchantApiLog()->logRequest($request, $merchant, $request->validated());

        try {
            services()->antiFraud()->check($merchant, $request->client_id);
        } catch (AntiFraudException $e) {
            $response = response()->failWithMessage($e->getMessage());
            services()->merchantApiLog()->updateWithResponse($merchant, $request->external_id, $requestId, $response);

            return $response;
        }



        $timeout = (int)$merchant->max_order_wait_time;
        if (request()->header('X-Max-Wait-Ms')) {
            $timeout = (int)request()->header('X-Max-Wait-Ms');
        }

        $timeout = $timeout === 0 ? config('order-pooling.max_wait_time') : $timeout;
        $timeout = $timeout < 1000 ? 1000 : $timeout;
        $timeout = $timeout > config('order-pooling.max_wait_time') ? config('order-pooling.max_wait_time') : $timeout;

        // Ожидание результата
        $maxWaitMs = $timeout;
        $intervalMs = config('order-pooling.poll_interval');
        $waited = 0;
        $processingTimeMs = 0;
        $maxWaitProcessingMs = 3000;

        $jobID = Str::uuid()->toString();
        $createdAt = now()->getTimestampMs();

        cache()->put("order:create:$jobID", json_encode([
            'status' => 'queued',
        ]), 60);


        $payload = $request->validated();
        if ($request instanceof H2HRequest) {
            $payload['h2h'] = true;
        }
        OrderPoolingJob::dispatch($jobID, $createdAt, $payload, $maxWaitMs);

        while ($waited < $maxWaitMs) {
            usleep($intervalMs * 1000);
            $waited += $intervalMs;

            $result = cache()->get("order:create:$jobID");

            if ($result) {
                $data = json_decode($result, true);

                if (empty($data['status'])) {
                    break;
                }

                if ($data['status'] === 'queued' && $waited > $maxWaitMs + ($intervalMs * 2)) {
                    cache()->put("order:create:$jobID", json_encode([
                        'status' => 'expired',
                    ]), 60);
                    break;
                }

                if ($data['status'] === 'done') {
                    /**
                     * @var Order $order
                     */
                    $order = Order::withoutGlobalScopes()->find($data['order_id']);

                    if ($request instanceof H2HRequest) {
                        $resource = H2HOrderResource::make($order);
                    } else {
                        $resource = MOrderResource::make($order);
                    }

                    // Обновляем лог с успешным ответом
                    $response = response()->success($resource);
                    services()->merchantApiLog()->updateWithResponse($merchant, $request->external_id, $requestId, $response, $order);

                    return $response;
                } elseif ($data['status'] === 'failed') {
                    if (empty($data['exception']['class']) || empty($data['exception']['message'])) {
                        $response = response()->failWithMessage('Произошла неизвестная ошибка при обработке запроса');
                        services()->merchantApiLog()->updateWithResponse($merchant, $request->external_id, $requestId, $response);

                        return $response;
                    }

                    if (is_a($data['exception']['class'], OrderException::class, true)) {
                        $response = response()->failWithMessage($data['exception']['message']);
                        services()->merchantApiLog()->updateWithResponse($merchant, $request->external_id, $requestId, $response, null, $data['exception']['class'], $data['exception']['message']);

                        return $response;
                    } elseif (is_a($data['exception']['class'], Throwable::class, true)) {
                        $response = response()->failWithMessage('Произошла ошибка при обработке запроса');
                        services()->merchantApiLog()->updateWithResponse($merchant, $request->external_id, $requestId, $response, null, $data['exception']['class'], $data['exception']['message']);

                        return $response;
                    } else {
                        $response = response()->failWithMessage('Произошла неизвестная ошибка при обработке запроса');
                        services()->merchantApiLog()->updateWithResponse($merchant, $request->external_id, $requestId, $response);

                        return $response;
                    }
                } elseif ($data['status'] === 'expired') {
                    break;
                } elseif ($data['status'] === 'processing') {
                    $processingTimeMs = $processingTimeMs + $intervalMs;

                    if ($processingTimeMs > $maxWaitProcessingMs) {
                        break;
                    }
                }
            } else {
                break;
            }
        }

        $response = response()->failWithMessage('Не удалось обработать запрос вовремя. Повторите попытку позже.', 504);
        services()->merchantApiLog()->updateWithResponse($merchant, $request->external_id, $requestId, $response);

        return $response;
    }
}
