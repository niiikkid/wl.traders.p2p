<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;

class IntegrationInfrastructureApiToken
{
    public const SETTING_KEY = 'integration_infrastructure_api_token';

    private const LEGACY_SETTING_KEY = 'integration_read_api_token';

    public static function get(): string
    {
        return cache()->rememberForever(self::cacheKey(), static function (): string {
            return self::ensureExists();
        });
    }

    public static function regenerate(): string
    {
        $token = self::generateToken();

        Setting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => $token],
        );

        cache()->forever(self::cacheKey(), $token);

        return $token;
    }

    private static function ensureExists(): string
    {
        $currentSetting = Setting::query()->where('key', self::SETTING_KEY)->first();

        if ($currentSetting) {
            return (string) $currentSetting->value;
        }

        $legacySetting = Setting::query()->where('key', self::LEGACY_SETTING_KEY)->first();

        if ($legacySetting) {
            Setting::query()->updateOrCreate(
                ['key' => self::SETTING_KEY],
                ['value' => $legacySetting->value],
            );

            return (string) $legacySetting->value;
        }

        $setting = Setting::query()->firstOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => self::generateToken()],
        );

        return (string) $setting->value;
    }

    private static function generateToken(): string
    {
        return strtolower(Str::random(48));
    }

    private static function cacheKey(): string
    {
        return 'integration-infrastructure-api-token';
    }
}
