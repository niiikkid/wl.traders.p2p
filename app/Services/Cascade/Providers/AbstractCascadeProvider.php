<?php

declare(strict_types=1);

namespace App\Services\Cascade\Providers;

use App\Models\CascadeDeal;
use App\Models\CascadeProvider;
use App\Services\Cascade\CascadeProviderOperationLogger;
use RuntimeException;
use Throwable;

/**
 * Абстрактный базовый класс для провайдеров каскада
 *
 * Предоставляет базовую реализацию интерфейса CascadeProviderInterface
 * Конкретные провайдеры должны наследоваться от этого класса и реализовывать
 * специфичную для них логику
 */
abstract class AbstractCascadeProvider implements CascadeProviderInterface
{
    public function __construct(
        protected string $code,
        protected array $config = [],
        protected ?CascadeProvider $providerModel = null,
        protected ?CascadeProviderOperationLogger $operationLogger = null,
    ) {}

    /**
     * Создать сделку у провайдера
     *
     * @param  CascadeDeal  $cascadeDeal  Каскадная сделка
     * @return array Данные созданной сделки у провайдера
     */
    abstract public function createDeal(CascadeDeal $cascadeDeal, ?int $maxWaitMs = null): array;

    /**
     * Отменить сделку у провайдера
     *
     * @param  CascadeDeal  $cascadeDeal  Каскадная сделка
     * @param  string  $providerDealId  ID сделки у провайдера
     * @return array Результат отмены
     */
    abstract public function cancelDeal(CascadeDeal $cascadeDeal, string $providerDealId): array;

    /**
     * Получить состояние сделки у провайдера
     *
     * @param  CascadeDeal  $cascadeDeal  Каскадная сделка
     * @param  string  $providerDealId  ID сделки у провайдера
     * @return array Данные сделки у провайдера
     */
    abstract public function getDeal(CascadeDeal $cascadeDeal, string $providerDealId): array;

    /**
     * Открыть спор у провайдера
     *
     * @param  CascadeDeal  $cascadeDeal  Каскадная сделка
     * @param  string  $providerDealId  ID сделки у провайдера
     * @param  array  $data  Данные для открытия спора (например, receipts)
     * @return array Данные созданного спора
     */
    abstract public function openDispute(CascadeDeal $cascadeDeal, string $providerDealId, array $data = []): array;

    /**
     * Получить состояние спора у провайдера
     *
     * @param  CascadeDeal  $cascadeDeal  Каскадная сделка
     * @param  string  $providerDealId  ID сделки у провайдера
     * @param  string  $disputeId  ID спора у провайдера
     * @return array Данные спора у провайдера
     */
    abstract public function getDispute(CascadeDeal $cascadeDeal, string $providerDealId, string $disputeId): array;

    /**
     * Обработать callback от провайдера
     *
     * @param  array  $payload  Данные callback'а
     * @return array Обработанные данные
     */
    public function handleCallback(array $payload): array
    {
        // Базовая реализация - просто возвращаем payload
        // Конкретные провайдеры могут переопределить этот метод
        return $payload;
    }

    /**
     * Получить уникальный код провайдера
     *
     * @return string Код провайдера
     */
    abstract public function getCode(): string;

    /**
     * @param  array<string, mixed>  $context
     */
    abstract public function providerApiLogUrl(string $operation, ?CascadeDeal $cascadeDeal = null, array $context = []): string;

    /**
     * Собирает абсолютный URL исходящего запроса к внешнему API провайдера.
     * Домен берётся только из настроек интеграции (base_url); запасных источников нет.
     *
     * @param  string  $path  Путь, начинающийся с «/»
     *
     * @throws RuntimeException
     */
    protected function integrationHttpUrl(string $configuredBaseUrl, string $path): string
    {
        $base = rtrim(trim($configuredBaseUrl), '/');
        if ($base === '') {
            throw new RuntimeException(
                sprintf('Базовый URL интеграции (base_url) не задан для провайдера каскада «%s».', $this->getCode()),
            );
        }

        return $base.$path;
    }

    /**
     * @param  array<string, mixed>|null  $requestPayload
     * @param  array<string, mixed>|null  $responsePayload
     * @param  array<string, mixed>  $context
     */
    protected function recordProviderOperation(
        CascadeDeal $cascadeDeal,
        string $operation,
        string $method,
        string $url,
        ?array $requestPayload,
        ?array $responsePayload,
        ?int $statusCode,
        float $startedAt,
        bool $isSuccessful,
        ?Throwable $exception = null,
        array $context = [],
    ): void {
        if (! $this->providerModel instanceof CascadeProvider || ! $this->operationLogger instanceof CascadeProviderOperationLogger) {
            return;
        }

        $this->operationLogger->providerOperation(
            provider: $this->providerModel,
            operation: $operation,
            method: $method,
            url: $url,
            deal: $cascadeDeal,
            requestPayload: $requestPayload,
            responsePayload: $responsePayload,
            statusCode: $statusCode,
            startedAt: $startedAt,
            isSuccessful: $isSuccessful,
            errorCode: $exception ? get_class($exception) : null,
            errorMessage: $exception?->getMessage(),
            context: $context,
        );
    }
}
