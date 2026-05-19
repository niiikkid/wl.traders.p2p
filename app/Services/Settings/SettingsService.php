<?php

namespace App\Services\Settings;

use App\Contracts\SettingsServiceContract;
use App\Enums\MarketEnum;
use App\Exceptions\SettingsException;
use App\Models\PaymentDetail;
use App\Models\Setting;
use App\Models\User;
use App\Models\ValueObjects\Settings\BinancePriceParserSettings;
use App\Models\ValueObjects\Settings\CurrencyPriceParserSettings;
use App\Models\ValueObjects\Settings\ManualPriceParserSettings;
use App\Models\ValueObjects\Settings\PrimeTimeSettings;
use App\Services\Money\Currency;

class SettingsService implements SettingsServiceContract
{
    const APP_SLOGAN = 'app_slogan';

    const PRIME_TIME_BONUS_STARTS = 'prime_time_bonus_starts';

    const PRIME_TIME_BONUS_ENDS = 'prime_time_bonus_ends';

    const PRIME_TIME_BONUS_RATE = 'prime_time_bonus_rate';

    const CURRENCY_PRICE_PARSER_SETTINGS = 'currency_price_parser_settings';

    const SUPPORT_LINK = 'support_link';

    const FUNDS_ON_HOLD_TIME = 'funds_on_hold_time';

    const MAX_PENDING_DISPUTES = 'max_pending_disputes';

    const MAX_REJECTED_DISPUTES = 'max_rejected_disputes';

    const TEMP_VIP_REQUIRED_DEALS = 'temp_vip_required_deals';

    const TEMP_VIP_DURATION_MINUTES = 'temp_vip_duration_minutes';

    const TEMP_VIP_ENABLED = 'temp_vip_enabled';

    const TRAFFIC_PAUSED = 'traffic_paused';

    const TRAFFIC_PAUSED_CACHE_KEY = 'settings_traffic_paused';

    const DEFAULT_RESERVE_BALANCE_LIMIT = 'default_reserve_balance_limit';

    const PAYOUT_CURRENCY_SETTINGS = 'payout_currency_settings';

    const PAYOUT_PRIORITY_ACCESS_ENABLED = 'payout_priority_access_enabled';

    const PAYOUT_PRIORITY_ACCESS_DELAY_MINUTES = 'payout_priority_access_delay_minutes';

    const PAYOUT_PRIORITY_ACCESS_RELEASE_WITHOUT_ONLINE_TRADERS = 'payout_priority_access_release_without_online_traders';

    const TRADER_ANALYTICS_OPERATION_THRESHOLDS = 'trader_analytics_operation_thresholds';

    protected $settings = null;

    public function getAppSlogan(): string
    {
        if (! $this->settings) {
            $settings = cache()->get('app-settings');

            if (! $settings) {
                $settings = cache()->rememberForever('app-settings', function () {
                    return Setting::all();
                });
            }

            $this->settings = $settings;
        }

        $value = $this->settings->where('key', self::APP_SLOGAN)->first()?->value;

        if (! is_string($value) || $value === '') {
            return 'Надежный процессинг';
        }

        return $value;
    }

    public function updateAppSlogan(string $value): void
    {
        Setting::updateOrCreate(
            ['key' => self::APP_SLOGAN],
            ['value' => trim($value)]
        );

        cache()->put('app-settings', Setting::all());
        $this->settings = null;
    }

    public function getPrimeTimeBonus(): PrimeTimeSettings
    {
        return new PrimeTimeSettings(
            starts: $this->getParam(self::PRIME_TIME_BONUS_STARTS),
            ends: $this->getParam(self::PRIME_TIME_BONUS_ENDS),
            rate: $this->getParam(self::PRIME_TIME_BONUS_RATE)
        );
    }

    public function updatePrimeTimeBonus(string $starts, string $ends, float $rate): void
    {
        $this->updateParam(self::PRIME_TIME_BONUS_STARTS, $starts);
        $this->updateParam(self::PRIME_TIME_BONUS_ENDS, $ends);
        $this->updateParam(self::PRIME_TIME_BONUS_RATE, round($rate, 2));
    }

    public function getMarketPriceParser(
        Currency $currency,
        MarketEnum $market
    ): CurrencyPriceParserSettings|BinancePriceParserSettings|ManualPriceParserSettings {
        $param = json_decode($this->getParam(self::CURRENCY_PRICE_PARSER_SETTINGS), true);
        $settings = $param[$currency->getCode()] ?? [];
        $settings = $this->normalizeMarketPriceParserSettings($settings);

        $marketKey = $market->value;
        $marketSettings = $settings[$marketKey] ?? [];

        return match (true) {
            $market->equals(MarketEnum::BINANCE) => BinancePriceParserSettings::fromArray($marketSettings),
            $market->equals(MarketEnum::MANUAL) => ManualPriceParserSettings::fromArray($marketSettings),
            default => CurrencyPriceParserSettings::fromArray($marketSettings),
        };
    }

    public function updateMarketPriceParser(
        Currency $currency,
        MarketEnum $market,
        CurrencyPriceParserSettings|BinancePriceParserSettings|ManualPriceParserSettings $settings
    ): void {
        $param = json_decode($this->getParam(self::CURRENCY_PRICE_PARSER_SETTINGS), true);
        $code = $currency->getCode();

        $current = $this->normalizeMarketPriceParserSettings($param[$code] ?? []);
        $current[$market->value] = $settings->toArray();

        $param[$code] = $current;

        $this->updateParam(self::CURRENCY_PRICE_PARSER_SETTINGS, $param);
    }

    public function getSupportLink(): ?string
    {
        return $this->getParam(self::SUPPORT_LINK);
    }

    public function updateSupportLink(string $link): void
    {
        $this->updateParam(self::SUPPORT_LINK, $link);
    }

    public function getFundsOnHoldTime(): int
    {
        return $this->getParam(self::FUNDS_ON_HOLD_TIME);
    }

    public function updateFundsOnHoldTime(int $minutes): void
    {
        $this->updateParam(self::FUNDS_ON_HOLD_TIME, $minutes);
    }

    public function getMaxPendingDisputes(): int
    {
        return $this->getParam(self::MAX_PENDING_DISPUTES);
    }

    public function updateMaxPendingDisputes(int $value): void
    {
        $this->updateParam(self::MAX_PENDING_DISPUTES, $value);
    }

    public function getMaxRejectedDisputes(): array
    {
        $value = $this->getParam(self::MAX_REJECTED_DISPUTES);
        if (! $value) {
            return ['count' => 0, 'period' => 0];
        }

        return json_decode($value, true);
    }

    public function updateMaxRejectedDisputes(int $count, int $period): void
    {
        $this->updateParam(self::MAX_REJECTED_DISPUTES, json_encode(['count' => $count, 'period' => $period]));
    }

    public function getTempVipRequiredDeals(): int
    {
        return (int) $this->getParam(self::TEMP_VIP_REQUIRED_DEALS);
    }

    public function updateTempVipRequiredDeals(int $value): void
    {
        $this->updateParam(self::TEMP_VIP_REQUIRED_DEALS, $value);
    }

    public function getTempVipDurationMinutes(): int
    {
        return (int) $this->getParam(self::TEMP_VIP_DURATION_MINUTES);
    }

    public function updateTempVipDurationMinutes(int $value): void
    {
        $this->updateParam(self::TEMP_VIP_DURATION_MINUTES, $value);
    }

    public function isTempVipEnabled(): bool
    {
        return (bool) (int) $this->getParam(self::TEMP_VIP_ENABLED);
    }

    public function updateTempVipEnabled(bool $enabled): void
    {
        $this->updateParam(self::TEMP_VIP_ENABLED, $enabled ? 1 : 0);

        if (! $enabled) {
            // Полное выключение функционала: сбрасываем временный VIP и прогресс для всех non-VIP пользователей,
            // и отключаем VIP-лимиты на реквизитах (перманентный VIP при этом не затрагиваем).
            $now = now();

            User::query()
                ->where('is_vip', false)
                ->update([
                    'temp_vip_active_until' => null,
                    'temp_vip_can_activate' => false,
                    'temp_vip_progress_start_at' => $now,
                ]);

            PaymentDetail::query()
                ->whereIn('user_id', User::query()->where('is_vip', false)->select('id'))
                ->update([
                    'min_order_amount' => null,
                    'max_order_amount' => null,
                ]);
        }
    }

    public function isTrafficPaused(): bool
    {
        return (bool) cache()->remember(self::TRAFFIC_PAUSED_CACHE_KEY, now()->addMinute(), function () {
            return (int) Setting::query()
                ->where('key', self::TRAFFIC_PAUSED)
                ->value('value') === 1;
        });
    }

    public function updateTrafficPaused(bool $paused): void
    {
        $this->updateParam(self::TRAFFIC_PAUSED, $paused ? 1 : 0);
        cache()->put(self::TRAFFIC_PAUSED_CACHE_KEY, $paused, now()->addMinute());
    }

    public function getDefaultReserveBalanceLimit(): int
    {
        return (int) $this->getParam(self::DEFAULT_RESERVE_BALANCE_LIMIT);
    }

    public function updateDefaultReserveBalanceLimit(int $value): void
    {
        $this->updateParam(self::DEFAULT_RESERVE_BALANCE_LIMIT, $value);
    }

    public function getPayoutCurrencySettings(): array
    {
        $value = $this->getParam(self::PAYOUT_CURRENCY_SETTINGS);
        $settings = json_decode($value, true);

        if (! is_array($settings)) {
            $settings = [];
        }

        return $this->normalizePayoutCurrencySettings($settings);
    }

    public function getPayoutSettingsForCurrency(Currency $currency): array
    {
        $settings = $this->getPayoutCurrencySettings();

        return $settings[$currency->getCode()] ?? $this->defaultPayoutCurrencySettings();
    }

    public function updatePayoutCurrencySettings(array $settings): void
    {
        $this->updateParam(
            self::PAYOUT_CURRENCY_SETTINGS,
            json_encode($this->normalizePayoutCurrencySettings($settings))
        );
    }

    public function getPayoutPriorityAccessSettings(): array
    {
        return [
            'enabled' => (bool) (int) $this->getParam(self::PAYOUT_PRIORITY_ACCESS_ENABLED),
            'delay_minutes' => max(1, (int) $this->getParam(self::PAYOUT_PRIORITY_ACCESS_DELAY_MINUTES)),
            'release_without_online_traders' => (bool) (int) $this->getParam(self::PAYOUT_PRIORITY_ACCESS_RELEASE_WITHOUT_ONLINE_TRADERS),
        ];
    }

    public function updatePayoutPriorityAccessSettings(
        bool $enabled,
        int $delayMinutes,
        bool $releaseWithoutOnlineTraders
    ): void {
        $this->updateParam(self::PAYOUT_PRIORITY_ACCESS_ENABLED, $enabled ? 1 : 0);
        $this->updateParam(self::PAYOUT_PRIORITY_ACCESS_DELAY_MINUTES, max(1, $delayMinutes));
        $this->updateParam(
            self::PAYOUT_PRIORITY_ACCESS_RELEASE_WITHOUT_ONLINE_TRADERS,
            $releaseWithoutOnlineTraders ? 1 : 0
        );
    }

    public function getTraderAnalyticsOperationThresholds(): array
    {
        $value = $this->getParam(self::TRADER_ANALYTICS_OPERATION_THRESHOLDS);
        $thresholds = json_decode($value, true);

        if (! is_array($thresholds)) {
            $thresholds = [];
        }

        return $this->normalizeTraderAnalyticsOperationThresholds($thresholds);
    }

    public function getTraderAnalyticsOperationThresholdForCurrency(Currency $currency): string
    {
        $thresholds = $this->getTraderAnalyticsOperationThresholds();

        return (string) ($thresholds[$currency->getCode()] ?? '300');
    }

    public function updateTraderAnalyticsOperationThreshold(string $currencyCode, string $threshold): void
    {
        $currency = Currency::make($currencyCode);
        $thresholds = $this->getTraderAnalyticsOperationThresholds();
        $thresholds[$currency->getCode()] = $threshold;

        $this->updateParam(
            self::TRADER_ANALYTICS_OPERATION_THRESHOLDS,
            json_encode($this->normalizeTraderAnalyticsOperationThresholds($thresholds))
        );
    }

    public function createAll(): void
    {
        cache()->forget('app-settings');

        Setting::firstOrCreate([
            'key' => self::APP_SLOGAN,
            'value' => 'Надежный процессинг',
        ]);
        Setting::firstOrCreate([
            'key' => self::PRIME_TIME_BONUS_STARTS,
            'value' => '00:00',
        ]);
        Setting::firstOrCreate([
            'key' => self::PRIME_TIME_BONUS_ENDS,
            'value' => '07:00',
        ]);
        Setting::firstOrCreate([
            'key' => self::PRIME_TIME_BONUS_RATE,
            'value' => '1.2',
        ]);
        Setting::firstOrCreate([
            'key' => self::SUPPORT_LINK,
            'value' => null,
        ]);
        Setting::firstOrCreate([
            'key' => self::FUNDS_ON_HOLD_TIME,
            'value' => 1440,
        ]);

        Setting::firstOrCreate([
            'key' => self::MAX_PENDING_DISPUTES,
            'value' => 5,
        ]);

        Setting::firstOrCreate([
            'key' => self::MAX_REJECTED_DISPUTES,
            'value' => json_encode(['count' => 10, 'period' => 60]),
        ]);

        Setting::firstOrCreate([
            'key' => self::TEMP_VIP_REQUIRED_DEALS,
            'value' => 30,
        ]);

        Setting::firstOrCreate([
            'key' => self::TEMP_VIP_DURATION_MINUTES,
            'value' => 120,
        ]);

        Setting::firstOrCreate([
            'key' => self::TEMP_VIP_ENABLED,
            'value' => 1,
        ]);

        Setting::firstOrCreate([
            'key' => self::TRAFFIC_PAUSED,
            'value' => 0,
        ]);

        Setting::firstOrCreate([
            'key' => self::DEFAULT_RESERVE_BALANCE_LIMIT,
            'value' => 500,
        ]);

        Setting::firstOrCreate([
            'key' => self::PAYOUT_CURRENCY_SETTINGS,
            'value' => json_encode($this->normalizePayoutCurrencySettings([])),
        ]);

        Setting::firstOrCreate([
            'key' => self::PAYOUT_PRIORITY_ACCESS_ENABLED,
            'value' => 0,
        ]);

        Setting::firstOrCreate([
            'key' => self::PAYOUT_PRIORITY_ACCESS_DELAY_MINUTES,
            'value' => 10,
        ]);

        Setting::firstOrCreate([
            'key' => self::PAYOUT_PRIORITY_ACCESS_RELEASE_WITHOUT_ONLINE_TRADERS,
            'value' => 1,
        ]);

        Setting::firstOrCreate([
            'key' => self::TRADER_ANALYTICS_OPERATION_THRESHOLDS,
            'value' => json_encode($this->normalizeTraderAnalyticsOperationThresholds([])),
        ]);

        $currenciesJson = $this->getParam(self::CURRENCY_PRICE_PARSER_SETTINGS);
        if (! empty($currenciesJson)) {
            $currencies = json_decode($currenciesJson, true);
        } else {
            $currencies = [];
        }

        Currency::getAll()->each(function (Currency $currency) use (&$currencies) {
            $code = $currency->getCode();
            $currencies[$code] = $this->normalizeMarketPriceParserSettings($currencies[$code] ?? []);
        });

        Setting::updateOrCreate(['key' => self::CURRENCY_PRICE_PARSER_SETTINGS], [
            'key' => self::CURRENCY_PRICE_PARSER_SETTINGS,
            'value' => json_encode($currencies),
        ]);

        cache()->put('app-settings', Setting::all());
    }

    protected function getParam(string $key): mixed
    {
        if (! $this->settings) {
            $settings = cache()->get('app-settings');

            if (! $settings) {
                $settings = cache()->rememberForever('app-settings', function () {
                    return Setting::all();
                });
            }

            $this->settings = $settings;
        }

        $setting = $this->settings->where('key', $key)->first();

        if (! $setting) {
            throw SettingsException::make("Параметр настроек '{$key}' не найден или пуст");
        }

        return $setting->value;
    }

    protected function updateParam(string $key, mixed $value): bool
    {
        $res = Setting::query()->where('key', $key)->update(['value' => $value]);

        cache()->put('app-settings', Setting::all());
        $this->settings = null;

        return (bool) $res;
    }

    protected function normalizeMarketPriceParserSettings(array $settings): array
    {
        if (isset($settings['bybit']) || isset($settings['binance']) || isset($settings['manual'])) {
            $settings['bybit'] = CurrencyPriceParserSettings::fromArray($settings['bybit'] ?? [])->toArray();
            $settings['binance'] = BinancePriceParserSettings::fromArray($settings['binance'] ?? [])->toArray();
            $settings['manual'] = ManualPriceParserSettings::fromArray($settings['manual'] ?? [])->toArray();

            return $settings;
        }

        return [
            'bybit' => CurrencyPriceParserSettings::fromArray($settings)->toArray(),
            'binance' => BinancePriceParserSettings::defaults()->toArray(),
            'manual' => ManualPriceParserSettings::defaults()->toArray(),
        ];
    }

    protected function normalizePayoutCurrencySettings(array $settings): array
    {
        $defaults = $this->defaultPayoutCurrencySettings();
        $normalized = [];

        Currency::getAll()->each(function (Currency $currency) use (&$normalized, $settings, $defaults) {
            $code = $currency->getCode();
            $current = $settings[$code] ?? $settings[strtoupper($code)] ?? null;
            $normalized[$code] = [
                'total_commission_rate' => isset($current['total_commission_rate'])
                    ? (float) $current['total_commission_rate']
                    : (float) $defaults['total_commission_rate'],
                'trader_commission_rate' => isset($current['trader_commission_rate'])
                    ? (float) $current['trader_commission_rate']
                    : (float) $defaults['trader_commission_rate'],
                'reservation_time_for_payouts' => isset($current['reservation_time_for_payouts'])
                    ? (int) $current['reservation_time_for_payouts']
                    : (int) $defaults['reservation_time_for_payouts'],
                'priority_access_min_amount' => $this->normalizeNullableAmount(
                    $current['priority_access_min_amount'] ?? null
                ),
                'priority_access_max_amount' => $this->normalizeNullableAmount(
                    $current['priority_access_max_amount'] ?? null
                ),
            ];
        });

        return $normalized;
    }

    protected function defaultPayoutCurrencySettings(): array
    {
        return [
            'total_commission_rate' => 5,
            'trader_commission_rate' => 4,
            'reservation_time_for_payouts' => 20,
            'priority_access_min_amount' => null,
            'priority_access_max_amount' => null,
        ];
    }

    protected function normalizeNullableAmount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], trim((string) $value));

        return is_numeric($normalized) ? $normalized : null;
    }

    protected function normalizeTraderAnalyticsOperationThresholds(array $thresholds): array
    {
        $normalized = [];

        Currency::getAll()->each(function (Currency $currency) use (&$normalized, $thresholds) {
            $code = $currency->getCode();
            $current = $thresholds[$code] ?? $thresholds[strtoupper($code)] ?? null;
            $value = is_numeric($current) ? (string) $current : '300';

            $normalized[$code] = $value;
        });

        return $normalized;
    }
}
