<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\CascadeProvider;
use App\Services\Cascade\Providers\CascadeProviderInterface;

/**
 * Контракт сервиса работы с провайдерами каскада
 */
interface CascadeProviderServiceContract
{
    /**
     * Получить провайдера по коду
     *
     * @param string $code Код провайдера
     * @return CascadeProviderInterface|null
     */
    public function getProvider(string $code): ?CascadeProviderInterface;

    /**
     * Получить провайдера по модели CascadeProvider
     *
     * @param CascadeProvider $provider Модель провайдера
     * @return CascadeProviderInterface|null
     */
    public function getProviderByModel(CascadeProvider $provider): ?CascadeProviderInterface;

    /**
     * Получить все активные провайдеры
     *
     * @return array<string, CascadeProviderInterface>
     */
    public function getActiveProviders(): array;

    /**
     * Получить все провайдеры (включая неактивные)
     *
     * @return array<string, CascadeProviderInterface>
     */
    public function getAllProviders(): array;

    /**
     * Получить список всех доступных кодов провайдеров
     *
     * @return array<string> Массив кодов провайдеров
     */
    public function getAvailableProviderCodes(): array;

    /**
     * Получить список кодов доступных интеграций (реализованных в коде)
     *
     * @return array<string> Массив кодов интеграций
     */
    public function getAvailableIntegrationCodes(): array;
}
