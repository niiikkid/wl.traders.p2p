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
     * @param CascadeDeal $cascadeDeal Каскадная сделка
     * @return array Данные созданной сделки у провайдера
     */
    public function createDeal(CascadeDeal $cascadeDeal): array;

    /**
     * Отменить сделку у провайдера
     *
     * @param CascadeDeal $cascadeDeal Каскадная сделка
     * @param string $providerDealId ID сделки у провайдера
     * @return array Результат отмены
     */
    public function cancelDeal(CascadeDeal $cascadeDeal, string $providerDealId): array;

    /**
     * Получить состояние сделки у провайдера
     *
     * @param CascadeDeal $cascadeDeal Каскадная сделка
     * @param string $providerDealId ID сделки у провайдера
     * @return array Данные сделки у провайдера
     */
    public function getDeal(CascadeDeal $cascadeDeal, string $providerDealId): array;

    /**
     * Открыть спор у провайдера
     *
     * @param CascadeDeal $cascadeDeal Каскадная сделка
     * @param string $providerDealId ID сделки у провайдера
     * @param array $data Данные для открытия спора (например, receipts)
     * @return array Данные созданного спора
     */
    public function openDispute(CascadeDeal $cascadeDeal, string $providerDealId, array $data = []): array;

    /**
     * Получить состояние спора у провайдера
     *
     * @param CascadeDeal $cascadeDeal Каскадная сделка
     * @param string $providerDealId ID сделки у провайдера
     * @param string $disputeId ID спора у провайдера
     * @return array Данные спора у провайдера
     */
    public function getDispute(CascadeDeal $cascadeDeal, string $providerDealId, string $disputeId): array;

    /**
     * Обработать callback от провайдера
     *
     * @param array $payload Данные callback'а
     * @return array Обработанные данные
     */
    public function handleCallback(array $payload): array;

    /**
     * Получить уникальный код провайдера
     *
     * @return string Код провайдера
     */
    public function getCode(): string;
}
