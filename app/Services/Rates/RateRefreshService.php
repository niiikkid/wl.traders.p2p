<?php

namespace App\Services\Rates;

use App\Enums\RateSourceType;
use App\Models\RateSource;
use App\Models\ValueObjects\Settings\BinancePriceParserSideSettings;
use App\Models\ValueObjects\Settings\CurrencyPriceParserSideSettings;
use App\Services\Market\Utils\Parser\BinanceParser;
use App\Services\Market\Utils\Parser\ByBitParser;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Utils\Transaction;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Refresh path for a single {@see RateSource}. Reads provider-specific settings
 * from the source, parses a fresh rate, and persists it durably (DB + cache).
 * An empty parse keeps the previously stored rate.
 */
class RateRefreshService
{
    public function refresh(RateSource $source): void
    {
        $currency = $source->quoteCurrency();

        try {
            $rate = $this->parse($source, $currency);
        } catch (Throwable $e) {
            $this->storeFailure($source, $e);

            if (! $this->shouldSkipReporting($e)) {
                report($e);
            }

            return;
        }

        if (! $rate->greaterThanZero()) {
            $this->storeEmpty($source);

            return;
        }

        $this->storeSuccess($source, $rate);
    }

    /**
     * Parse a rate for the given (possibly unsaved) source without persisting anything.
     * Used by the admin preview to validate settings before saving.
     *
     * @return array{status: string, side: string, rate: string|null, error: string|null}
     */
    public function preview(RateSource $source): array
    {
        $side = $this->resolveSide($source);

        try {
            $rate = $this->parse($source, $source->quoteCurrency());
        } catch (Throwable $e) {
            return [
                'status' => 'failed',
                'side' => $side,
                'rate' => null,
                'error' => Str::limit($e->getMessage(), 300),
            ];
        }

        if (! $rate->greaterThanZero()) {
            return [
                'status' => 'empty',
                'side' => $side,
                'rate' => null,
                'error' => null,
            ];
        }

        return [
            'status' => 'success',
            'side' => $side,
            'rate' => $rate->toBeauty(),
            'error' => null,
        ];
    }

    protected function parse(RateSource $source, Currency $currency): Money
    {
        $settings = $source->settings ?? [];
        $p2pSide = $this->resolveSide($source);

        return match ($source->type) {
            RateSourceType::MANUAL => $this->manualRate($settings, $currency),
            RateSourceType::BYBIT => (new ByBitParser)->parseSourceRate(
                $currency,
                CurrencyPriceParserSideSettings::fromArray($settings),
                $p2pSide
            ),
            RateSourceType::BINANCE => (new BinanceParser)->parseSourceRate(
                $currency,
                BinancePriceParserSideSettings::fromArray($settings),
                $p2pSide
            ),
        };
    }

    protected function manualRate(array $settings, Currency $currency): Money
    {
        $rate = isset($settings['rate']) ? (float) $settings['rate'] : 0.0;

        return Money::fromPrecision((string) max(0, $rate), $currency);
    }

    /**
     * The parser side (buy/sell) is configured explicitly per source. Defaults to "sell".
     */
    protected function resolveSide(RateSource $source): string
    {
        $configured = $source->settings['side'] ?? null;

        return in_array($configured, ['buy', 'sell'], true) ? $configured : 'sell';
    }

    protected function storeSuccess(RateSource $source, Money $rate): void
    {
        Transaction::run(function () use ($source, $rate) {
            $locked = RateSource::query()->whereKey($source->id)->lockForUpdate()->first();

            if (! $locked) {
                return;
            }

            $locked->update([
                'rate' => $rate,
                'rate_currency' => $rate->getCurrency()->getCode(),
                'last_refreshed_at' => now(),
                'last_parse_attempt' => [
                    'status' => 'success',
                    'side' => $this->resolveSide($locked),
                    'rate' => $rate->toPrecision(),
                    'error' => null,
                    'attempted_at' => now()->toIso8601String(),
                ],
            ]);

            $source->setRawAttributes($locked->getAttributes());
        });

        RateSourceStore::put($source, $rate);
    }

    protected function storeEmpty(RateSource $source): void
    {
        $source->update([
            'last_refreshed_at' => now(),
            'last_parse_attempt' => [
                'status' => 'empty',
                'side' => $this->resolveSide($source),
                'rate' => $source->rate?->toPrecision(),
                'error' => null,
                'attempted_at' => now()->toIso8601String(),
            ],
        ]);
    }

    protected function storeFailure(RateSource $source, Throwable $e): void
    {
        $source->update([
            'last_refreshed_at' => now(),
            'last_parse_attempt' => [
                'status' => 'failed',
                'side' => $this->resolveSide($source),
                'rate' => $source->rate?->toPrecision(),
                'error' => Str::limit($e->getMessage(), 300),
                'attempted_at' => now()->toIso8601String(),
            ],
        ]);
    }

    protected function shouldSkipReporting(Throwable $exception): bool
    {
        $current = $exception;

        while ($current instanceof Throwable) {
            if ($current instanceof ConnectException) {
                $message = $current->getMessage();

                if (str_contains($message, 'cURL error 35') || str_contains($message, 'cURL error 28')) {
                    return true;
                }
            }

            if (
                $current instanceof \ErrorException
                && str_contains($current->getMessage(), 'Trying to access array offset on null')
            ) {
                return true;
            }

            $current = $current->getPrevious();
        }

        return false;
    }
}
