<?php

declare(strict_types=1);

namespace App\Services\Landing;

use App\Enums\OrderStatus;
use App\Models\MerchantApiRequestLog;
use App\Models\Order;
use App\Models\Payout\Payout;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Публичные агрегаты для лендинга (короткий кэш, чтобы не нагружать БД).
 */
final class LandingPublicStatsService
{
    private const CACHE_KEY = 'landing_public_stats_v2';

    private const CACHE_TTL_SECONDS = 60;

    /**
     * @return array{
     *     period_label: string,
     *     orders_total_usdt: string,
     *     today: array{
     *         api_volume_usdt: string,
     *         payouts_volume_usdt: string,
     *         avg_processing_minutes: float|null,
     *     },
     * }
     */
    public function getSnapshot(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $tz = config('app.timezone') ?: 'UTC';
            $day_start = CarbonImmutable::now($tz)->startOfDay();
            $day_end = $day_start->addDay();

            return [
                'period_label' => 'Показатели ниже — за сегодня.',
                'orders_total_usdt' => $this->beautyUsdt($this->sumGroupedFiatRowsToUsdt($this->orderAmountAggregates())),
                'today' => [
                    'api_volume_usdt' => $this->beautyUsdt($this->sumGroupedFiatRowsToUsdt($this->apiLogAmountAggregates($day_start, $day_end))),
                    'payouts_volume_usdt' => $this->beautyUsdt($this->sumPayoutsUsdtBetween($day_start, $day_end)),
                    'avg_processing_minutes' => $this->avgSuccessfulOrderProcessingMinutesToday($day_start, $day_end),
                ],
            ];
        });
    }

    /**
     * @return Collection<int, object{fiat_currency: string, total_units: string}>
     */
    private function orderAmountAggregates(): Collection
    {
        return Order::query()
            ->whereNotNull('amount')
            ->where('amount', '!=', '')
            ->whereNotNull('currency')
            ->selectRaw('LOWER(currency) as fiat_currency, SUM(CAST(amount AS DECIMAL(65,0))) as total_units')
            ->groupBy(DB::raw('LOWER(currency)'))
            ->get();
    }

    /**
     * @return Collection<int, object{fiat_currency: string, total_units: string}>
     */
    private function apiLogAmountAggregates(CarbonImmutable $day_start, CarbonImmutable $day_end): Collection
    {
        return MerchantApiRequestLog::query()
            ->where('created_at', '>=', $day_start)
            ->where('created_at', '<', $day_end)
            ->whereNotNull('amount')
            ->where('amount', '!=', '')
            ->whereNotNull('currency')
            ->where('currency', '!=', '')
            ->selectRaw('LOWER(currency) as fiat_currency, SUM(CAST(amount AS DECIMAL(65,0))) as total_units')
            ->groupBy(DB::raw('LOWER(currency)'))
            ->get();
    }

    /**
     * @param  Collection<int, object{fiat_currency: string, total_units: string}>  $rows
     */
    private function sumGroupedFiatRowsToUsdt(Collection $rows): Money
    {
        $total = Money::zero('usdt');

        foreach ($rows as $row) {
            $code = strtolower(trim((string) $row->fiat_currency));
            if ($code === '') {
                continue;
            }

            $units = preg_replace('/\s+/', '', (string) $row->total_units) ?? '';
            if ($units === '' || bccomp($units, '0', 0) !== 1) {
                continue;
            }

            try {
                if ($code === 'usdt') {
                    $total = $total->add(Money::fromUnits($units, 'usdt'));

                    continue;
                }

                $fiat = Money::fromUnits($units, $code);
                $rate = services()->market()->getSellPrice(Currency::make(strtoupper($code)));

                if ($rate->greaterThanZero()) {
                    $total = $total->add($fiat->convert($rate, Currency::USDT()));
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $total;
    }

    private function sumPayoutsUsdtBetween(CarbonImmutable $day_start, CarbonImmutable $day_end): Money
    {
        $sum = Payout::query()
            ->where('created_at', '>=', $day_start)
            ->where('created_at', '<', $day_end)
            ->whereNotNull('usdt_body')
            ->where('usdt_body', '!=', '')
            ->selectRaw('SUM(CAST(usdt_body AS DECIMAL(65,0))) as total_units')
            ->value('total_units');

        $units = is_string($sum) ? preg_replace('/\s+/', '', $sum) : (string) $sum;
        if ($units === '' || $units === '0' || bccomp($units, '0', 0) !== 1) {
            return Money::zero('usdt');
        }

        return Money::fromUnits($units, 'usdt');
    }

    /**
     * Среднее время от создания до успешного закрытия среди сделок, завершённых сегодня.
     */
    private function avgSuccessfulOrderProcessingMinutesToday(
        CarbonImmutable $day_start,
        CarbonImmutable $day_end
    ): ?float {
        $avg_seconds = Order::query()
            ->where('status', OrderStatus::SUCCESS)
            ->whereNotNull('finished_at')
            ->whereNotNull('created_at')
            ->where('finished_at', '>=', $day_start)
            ->where('finished_at', '<', $day_end)
            ->whereColumn('finished_at', '>=', 'created_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, finished_at)) as avg_sec')
            ->value('avg_sec');

        if ($avg_seconds === null) {
            return null;
        }

        $sec = (float) $avg_seconds;
        if ($sec <= 0.0) {
            return null;
        }

        return round($sec / 60.0, 1);
    }

    private function beautyUsdt(Money $money): string
    {
        return trim($money->toBeauty()).' USDT';
    }
}
