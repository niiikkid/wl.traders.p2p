<?php

namespace App\Services\AntiFraud;

use App\Contracts\AntiFraudSettingServiceContract;
use App\Models\AntiFraudSetting;
use Illuminate\Support\Facades\Cache;

class AntiFraudSettingService implements AntiFraudSettingServiceContract
{
    public function getForMerchant(int $merchantId): ?AntiFraudSetting
    {
        $key = $this->getCacheKey($merchantId);

        return Cache::rememberForever($key, function () use ($merchantId) {
            return AntiFraudSetting::query()
                ->where('merchant_id', $merchantId)
                ->first();
        });
    }

    public function create(array $data): AntiFraudSetting
    {
        $data = $this->normalize($data);
        $setting = AntiFraudSetting::query()->create($data);

        $this->storeCache($setting);

        return $setting;
    }

    public function update(AntiFraudSetting $setting, array $data): AntiFraudSetting
    {
        $originalMerchantId = $setting->merchant_id;
        $data = $this->normalize($data);

        $setting->update($data);
        $setting->refresh();

        if ($originalMerchantId !== $setting->merchant_id) {
            $this->forgetCache($originalMerchantId);
        }

        $this->storeCache($setting);

        return $setting;
    }

    public function delete(AntiFraudSetting $setting): void
    {
        $merchantId = $setting->merchant_id;
        $setting->delete();

        $this->forgetCache($merchantId);
    }

    private function normalize(array $data): array
    {
        $data['enabled'] = (bool) ($data['enabled'] ?? false);
        $data['secondary_enabled'] = (bool) ($data['secondary_enabled'] ?? true);
        $data['primary_rate_limits'] = $this->normalizeRateLimits($data['primary_rate_limits'] ?? []);
        $data['secondary_rate_limits'] = $this->normalizeRateLimits($data['secondary_rate_limits'] ?? []);

        return $data;
    }

    private function normalizeRateLimits(array $limits): array
    {
        return collect($limits)
            ->filter(fn (array $limit) => ! empty($limit['count']) && ! empty($limit['minutes']))
            ->map(fn (array $limit) => [
                'count' => (int) $limit['count'],
                'minutes' => (int) $limit['minutes'],
            ])
            ->values()
            ->toArray();
    }

    private function storeCache(AntiFraudSetting $setting): void
    {
        Cache::forever($this->getCacheKey($setting->merchant_id), $setting);
    }

    private function forgetCache(int $merchantId): void
    {
        Cache::forget($this->getCacheKey($merchantId));
    }

    private function getCacheKey(int $merchantId): string
    {
        return "anti_fraud_settings:merchant:{$merchantId}";
    }
}
