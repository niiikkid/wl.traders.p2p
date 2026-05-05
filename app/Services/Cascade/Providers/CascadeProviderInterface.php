<?php

declare(strict_types=1);

namespace App\Services\Cascade\Providers;

use App\Models\CascadeDeal;

/**
 * Интерфейс провайдера каскада
 *
 * Унифицированный контракт для работы с провайдерами ликвидности
 * (внутренними и внешними сервисами)
 */
interface CascadeProviderInterface
{
    /**
     * Создать сделку у провайдера
     *
     * @param  CascadeDeal  $cascadeDeal  Каскадная сделка
     * @return array Данные созданной сделки у провайдера
     */
    public function createDeal(CascadeDeal $cascadeDeal, ?int $maxWaitMs = null): array;

    /**
     * Отменить сделку у провайдера
     *
     * @param  CascadeDeal  $cascadeDeal  Каскадная сделка
     * @param  string  $providerDealId  ID сделки у провайдера
     * @return array Результат отмены
     */
    public function cancelDeal(CascadeDeal $cascadeDeal, string $providerDealId): array;

    /**
     * Получить состояние сделки у провайдера
     *
     * @param  CascadeDeal  $cascadeDeal  Каскадная сделка
     * @param  string  $providerDealId  ID сделки у провайдера
     * @return array Данные сделки у провайдера
     */
    public function getDeal(CascadeDeal $cascadeDeal, string $providerDealId): array;

    /**
     * Сохранить код подтверждения для сделки у провайдера
     *
     * @param  CascadeDeal  $cascadeDeal  Каскадная сделка
     * @param  string  $confirmationCode  Код подтверждения
     * @return array Данные созданного кода
     */
    public function storeConfirmationCode(CascadeDeal $cascadeDeal, string $confirmationCode): array;

    /**
     * Открыть спор у провайдера
     *
     * @param  CascadeDeal  $cascadeDeal  Каскадная сделка
     * @param  string  $providerDealId  ID сделки у провайдера
     * @param  array  $data  Данные для открытия спора (например, receipts)
     * @return array Данные созданного спора
     */
    public function openDispute(CascadeDeal $cascadeDeal, string $providerDealId, array $data = []): array;

    /**
     * Обработать callback от провайдера
     *
     * @param  array  $payload  Данные callback'а
     * @return array Обработанные данные
     */
    public function handleCallback(array $payload): array;

    /**
     * Получить уникальный код провайдера
     *
     * @return string Код провайдера
     */
    public function getCode(): string;

    /**
     * Полный URL эндпоинта исходящего запроса к провайдеру (для логов).
     *
     * @param  array<string, mixed>  $context  Например provider_deal_id, dispute_id
     */
    public function providerApiLogUrl(string $operation, ?CascadeDeal $cascadeDeal = null, array $context = []): string;
}
