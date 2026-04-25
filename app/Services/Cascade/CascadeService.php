<?php

declare(strict_types=1);

namespace App\Services\Cascade;

use App\Contracts\CascadeServiceContract;
use App\DTO\Cascade\CreateCascadeDealDTO;
use App\Exceptions\CascadeException;
use App\Jobs\CascadePoolingJob;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Models\CascadeDeal;
use Illuminate\Support\Str;
use Throwable;

/**
 * Центральный сервис каскада.
 *
 * На текущем этапе содержит только контрактный метод создания каскадной сделки
 * (без запуска каскадной логики и провайдеров).
 */
class CascadeService implements CascadeServiceContract
{
    public function createDeal(CreateCascadeDealDTO $dto): CascadeDeal
    {
        $timeout = 30 * 1000;

        $max_wait_ms = $timeout;
        $interval_ms = 100;
        $waited = 0;
        $processing_time_ms = 0;
        $max_wait_processing_ms = 3000;

        $job_id = Str::uuid()->toString();
        $created_at = now()->getTimestampMs();

        cache()->put("cascade:deal:create:$job_id", json_encode([
            'status' => 'queued',
        ]), 60);

        $payload = [
            'merchant_id' => $dto->merchantId,
            'external_id' => $dto->externalId,
            'amount' => $dto->amount,
            'currency' => $dto->currency,
            'payment_method' => $dto->paymentMethod->value,
            'callback_url' => $dto->callbackUrl,
            'client_id' => $dto->clientId,
        ];

        CascadePoolingJob::dispatch($job_id, $created_at, $payload, $max_wait_ms);

        while ($waited < $max_wait_ms) {
            usleep($interval_ms * 1000);
            $waited += $interval_ms;

            $result = cache()->get("cascade:deal:create:$job_id");

            if ($result) {
                $data = json_decode($result, true);

                if (empty($data['status'])) {
                    break;
                }

                if ($data['status'] === 'queued' && $waited > $max_wait_ms + ($interval_ms * 2)) {
                    cache()->put("cascade:deal:create:$job_id", json_encode([
                        'status' => 'expired',
                    ]), 60);
                    break;
                }

                if ($data['status'] === 'done') {
                    $cascade_deal = CascadeDeal::find($data['cascade_deal_id']);

                    if (! $cascade_deal) {
                        throw CascadeException::make('Не удалось получить каскадную сделку.');
                    }

                    return $cascade_deal;
                } elseif ($data['status'] === 'failed') {
                    if (empty($data['exception']['class']) || empty($data['exception']['message'])) {
                        throw CascadeException::make('Произошла неизвестная ошибка при обработке запроса');
                    }

                    if (is_a($data['exception']['class'], CascadeException::class, true)) {
                        throw CascadeException::make($data['exception']['message']);
                    } elseif (is_a($data['exception']['class'], Throwable::class, true)) {
                        throw CascadeException::make('Произошла ошибка при обработке запроса');
                    }

                    throw CascadeException::make('Произошла неизвестная ошибка при обработке запроса');
                } elseif ($data['status'] === 'expired') {
                    break;
                } elseif ($data['status'] === 'processing') {
                    $processing_time_ms = $processing_time_ms + $interval_ms;

                    if ($processing_time_ms > $max_wait_processing_ms) {
                        break;
                    }
                }
            } else {
                break;
            }
        }

        throw CascadeException::make('Не удалось обработать запрос вовремя. Повторите попытку позже.');
    }
}

