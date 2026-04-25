<?php

declare(strict_types=1);

namespace App\Services\Cascade\Providers;

use App\Models\CascadeDeal;

/**
 * Абстрактный базовый класс для провайдеров каскада
 *
 * Предоставляет базовую реализацию интерфейса CascadeProviderInterface
 * Конкретные провайдеры должны наследоваться от этого класса и реализовывать
 * специфичную для них логику
 */
abstract class AbstractCascadeProvider implements CascadeProviderInterface
{
    /**
     * Создать сделку у провайдера
     *
     * @param CascadeDeal $cascadeDeal Каскадная сделка
     * @return array Данные созданной сделки у провайдера
     */
    abstract public function createDeal(CascadeDeal $cascadeDeal): array;

    /**
     * Отменить сделку у провайдера
     *
     * @param CascadeDeal $cascadeDeal Каскадная сделка
     * @param string $providerDealId ID сделки у провайдера
     * @return array Результат отмены
     */
    abstract public function cancelDeal(CascadeDeal $cascadeDeal, string $providerDealId): array;

    /**
     * Получить состояние сделки у провайдера
     *
     * @param CascadeDeal $cascadeDeal Каскадная сделка
     * @param string $providerDealId ID сделки у провайдера
     * @return array Данные сделки у провайдера
     */
    abstract public function getDeal(CascadeDeal $cascadeDeal, string $providerDealId): array;

    /**
     * Открыть спор у провайдера
     *
     * @param CascadeDeal $cascadeDeal Каскадная сделка
     * @param string $providerDealId ID сделки у провайдера
     * @param array $data Данные для открытия спора (например, receipts)
     * @return array Данные созданного спора
     */
    abstract public function openDispute(CascadeDeal $cascadeDeal, string $providerDealId, array $data = []): array;

    /**
     * Получить состояние спора у провайдера
     *
     * @param CascadeDeal $cascadeDeal Каскадная сделка
     * @param string $providerDealId ID сделки у провайдера
     * @param string $disputeId ID спора у провайдера
     * @return array Данные спора у провайдера
     */
    abstract public function getDispute(CascadeDeal $cascadeDeal, string $providerDealId, string $disputeId): array;

    /**
     * Обработать callback от провайдера
     *
     * @param array $payload Данные callback'а
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
}
