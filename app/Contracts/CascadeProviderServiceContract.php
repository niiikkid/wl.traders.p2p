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
     * @param  string  $code  Код провайдера
     */
    public function getProvider(string $code): ?CascadeProviderInterface;

    /**
     * Получить провайдера по модели CascadeProvider
     *
     * @param  CascadeProvider  $provider  Модель провайдера
     */
    public function getProviderByModel(CascadeProvider $provider): ?CascadeProviderInterface;

    /**
     * Получить все активные провайдеры
     *
     * @return array<int, CascadeProviderInterface>
     */
    public function getActiveProviders(): array;

    /**
     * Получить все провайдеры (включая неактивные)
     *
     * @return array<int, CascadeProviderInterface>
     */
    public function getAllProviders(): array;

    /**
     * Получить список кодов провайдеров, зарегистрированных в базе
     *
     * @return array<string> Массив кодов провайдеров
     */
    public function getRegisteredProviderCodes(): array;

    /**
     * Получить список кодов интеграций, реализованных в коде
     *
     * @return array<string> Массив кодов интеграций
     */
    public function getImplementedIntegrationCodes(): array;
}
