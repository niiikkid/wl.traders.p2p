<?php

declare(strict_types=1);

namespace App\Services\Cascade;

use App\Contracts\CascadeProviderServiceContract;
use App\Models\CascadeProvider;
use App\Services\Cascade\Providers\CascadeProviderInterface;
use App\Services\Cascade\Providers\ExampleCascadeProvider;
use App\Services\Cascade\Providers\InternalCascadeProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Сервис работы с провайдерами каскада
 *
 * Предоставляет интерфейс для получения и работы с провайдерами ликвидности
 */
class CascadeProviderService implements CascadeProviderServiceContract
{
    /**
     * Кэш загруженных провайдеров
     *
     * @var array<string, CascadeProviderInterface>
     */
    private array $providersCache = [];

    /**
     * Получить провайдера по коду
     *
     * @param string $code Код провайдера
     * @return CascadeProviderInterface|null
     */
    public function getProvider(string $code): ?CascadeProviderInterface
    {
        if (isset($this->providersCache[$code])) {
            return $this->providersCache[$code];
        }

        $providerModel = CascadeProvider::where('code', $code)->first();

        if (!$providerModel) {
            return null;
        }

        return $this->getProviderByModel($providerModel);
    }

    /**
     * Получить провайдера по модели CascadeProvider
     *
     * @param CascadeProvider $provider Модель провайдера
     * @return CascadeProviderInterface|null
     */
    public function getProviderByModel(CascadeProvider $provider): ?CascadeProviderInterface
    {
        if (isset($this->providersCache[$provider->code])) {
            return $this->providersCache[$provider->code];
        }

        $providerInstance = $this->createProviderInstance($provider);

        if ($providerInstance) {
            $this->providersCache[$provider->code] = $providerInstance;
        }

        return $providerInstance;
    }

    /**
     * Получить все активные провайдеры
     *
     * @return array<string, CascadeProviderInterface>
     */
    public function getActiveProviders(): array
    {
        return CascadeProvider::where('is_active', true)
            ->get()
            ->mapWithKeys(function (CascadeProvider $provider) {
                $instance = $this->getProviderByModel($provider);
                return $instance ? [$provider->code => $instance] : [];
            })
            ->toArray();
    }

    /**
     * Получить все провайдеры (включая неактивные)
     *
     * @return array<string, CascadeProviderInterface>
     */
    public function getAllProviders(): array
    {
        return CascadeProvider::all()
            ->mapWithKeys(function (CascadeProvider $provider) {
                $instance = $this->getProviderByModel($provider);
                return $instance ? [$provider->code => $instance] : [];
            })
            ->toArray();
    }

    /**
     * Получить список всех доступных кодов провайдеров
     *
     * @return array<string> Массив кодов провайдеров
     */
    public function getAvailableProviderCodes(): array
    {
        // Получаем коды из базы данных (из модели CascadeProvider)
        // Это гарантирует, что мы возвращаем только те провайдеры, которые реально зарегистрированы
        return CascadeProvider::pluck('code')->toArray();
    }

    /**
     * Получить список кодов доступных интеграций (реализованных в коде)
     *
     * @return array<string> Массив кодов интеграций
     */
    public function getAvailableIntegrationCodes(): array
    {
        return array_values(array_filter(
            array_keys($this->getProviderClassMap()),
            fn (string $code) => $code !== InternalCascadeProvider::CODE
        ));
    }

    /**
     * Создать экземпляр провайдера на основе модели
     *
     * @param CascadeProvider $provider Модель провайдера
     * @return CascadeProviderInterface|null
     */
    private function createProviderInstance(CascadeProvider $provider): ?CascadeProviderInterface
    {
        $providerClass = $this->getProviderClassMap()[$provider->code] ?? null;

        if (!$providerClass || !class_exists($providerClass)) {
            Log::warning('Cascade provider class not found', [
                'code' => $provider->code,
                'class' => $providerClass,
            ]);

            return null;
        }

        try {
            $config = [
                'base_url' => $provider->base_url,
                'access_token' => $provider->access_token,
                'merchant_id' => $provider->merchant_id,
                'callback_url' => $provider->callback_url,
                'currency_code' => $provider->currency_code,
                'timeout' => $provider->timeout,
                'verify_ssl' => $provider->verify_ssl,
            ];
            return new $providerClass($provider->code, $config);
        } catch (\Throwable $e) {
            // Логируем ошибку создания провайдера
            Log::error('Failed to create cascade provider instance', [
                'code' => $provider->code,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Маппинг кодов провайдеров на классы реализации
     *
     * @return array<string, class-string<CascadeProviderInterface>>
     */
    private function getProviderClassMap(): array
    {
        return [
            'example' => ExampleCascadeProvider::class,
            InternalCascadeProvider::CODE => InternalCascadeProvider::class,
            // TODO: Добавить другие провайдеры по мере их реализации
            // 'external_provider_1' => ExternalProvider1::class,
        ];
    }
}
