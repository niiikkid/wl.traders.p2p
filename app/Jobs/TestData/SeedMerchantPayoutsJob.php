<?php

namespace App\Jobs\TestData;

use App\DTO\Payout\PayoutCreateDTO;
use App\Enums\PayoutMethodType;
use App\Models\Merchant;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Support\TestData\DemoDataHelper;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Создаёт партию выплат (pay-out) для одного мерчанта с разными исходами жизненного
 * цикла (open → taken → sent → completed / canceled) и распределением по времени.
 *
 * @property array<int, int> $traderIds
 */
class SeedMerchantPayoutsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * @param  array<int, int>  $traderIds
     */
    public function __construct(
        private readonly int $merchantId,
        private readonly int $count,
        private readonly array $traderIds,
        private readonly int $days = 30,
    ) {
        $this->onQueue('test-data');
    }

    public function handle(): void
    {
        DemoDataHelper::seedMarketPrices();

        $merchant = Merchant::query()
            ->with('wallet')
            ->whereKey($this->merchantId)
            ->whereNotNull('validated_at')
            ->whereNull('banned_at')
            ->where('active', true)
            ->first();

        if (! $merchant || ! $merchant->wallet || $this->traderIds === []) {
            return;
        }

        $currencies = $this->resolveCurrencies($merchant);

        for ($i = 0; $i < $this->count; $i++) {
            try {
                $this->createOne($merchant, $currencies);
            } catch (\Throwable $e) {
                Log::warning('[demo-data] payout creation skipped: '.$e->getMessage());
            }
        }
    }

    /**
     * @param  array<int, string>  $currencies
     */
    private function createOne(Merchant $merchant, array $currencies): void
    {
        $currencyCode = $currencies[array_rand($currencies)];
        $gateway = $this->pickGateway($currencyCode);
        $methodType = random_int(0, 1) === 0 ? PayoutMethodType::SBP : PayoutMethodType::CARD;
        $requisites = $methodType === PayoutMethodType::SBP
            ? DemoDataHelper::phoneForCurrency($currencyCode)
            : DemoDataHelper::generateCard();

        $amount = DemoDataHelper::realisticFiatAmount($currencyCode);

        $payout = services()->payout()->create(new PayoutCreateDTO(
            merchant: $merchant,
            paymentGateway: $gateway,
            externalId: 'demo-payout-'.Str::uuid()->toString(),
            amountFiat: Money::fromPrecision((string) $amount, $currencyCode),
            methodType: $methodType,
            requisites: $requisites,
            initials: DemoDataHelper::initials(),
            currencyCode: $currencyCode,
            callbackUrl: null,
            bankName: DemoDataHelper::bankName(),
        ));

        $createdAt = Carbon::now()
            ->subDays(random_int(0, max(0, $this->days)))
            ->setTime(random_int(6, 23), random_int(0, 59), random_int(0, 59));

        $roll = random_int(1, 100);

        if ($roll <= 10) {
            // Отменена до взятия.
            services()->payout()->cancel($payout);
            $this->backdate($payout->id, $createdAt, ['canceled_at' => $createdAt]);

            return;
        }

        if ($roll <= 22) {
            // Осталась открытой (в пуле, ждёт трейдера).
            $this->backdate($payout->id, $createdAt);

            return;
        }

        $trader = $this->pickTrader();
        if (! $trader) {
            $this->backdate($payout->id, $createdAt);

            return;
        }

        $payout = services()->payout()->take($payout, $trader);
        $takenAt = (clone $createdAt)->addMinutes(random_int(1, 30));

        if ($roll <= 40) {
            // Взята трейдером (прикреплена), но ещё не отправлена.
            $this->backdate($payout->id, $createdAt, ['taken_at' => $takenAt]);

            return;
        }

        $sentAt = (clone $takenAt)->addMinutes(random_int(1, 20));

        if ($roll <= 68) {
            // Отправлена и находится на удержании (статус SENT).
            // Включаем hold с большим окном, чтобы выплата осталась в статусе «отправлена».
            $trader->payout_hold_enabled = true;
            $trader->payout_hold_minutes = 2880;
            services()->payout()->markSent($payout, $trader);

            $this->backdate($payout->id, $createdAt, [
                'taken_at' => $takenAt,
                'sent_at' => $sentAt,
            ]);

            return;
        }

        // Отправлена и зачислена трейдеру (hold отключён — мгновенное завершение).
        $trader->payout_hold_enabled = false;
        services()->payout()->markSent($payout, $trader);

        $this->backdate($payout->id, $createdAt, [
            'taken_at' => $takenAt,
            'sent_at' => $sentAt,
            'completed_at' => $sentAt,
        ]);
    }

    /**
     * @param  array<string, Carbon>  $extra
     */
    private function backdate(int $payoutId, Carbon $createdAt, array $extra = []): void
    {
        $update = array_merge([
            'created_at' => $createdAt,
            'rate_fixed_at' => $createdAt,
            'expires_at' => (clone $createdAt)->addMinutes(30),
            'updated_at' => $createdAt,
        ], $extra);

        DB::table('payouts')->where('id', $payoutId)->update($update);
    }

    private function pickTrader(): ?User
    {
        $traderId = $this->traderIds[array_rand($this->traderIds)];

        return User::query()
            ->whereKey($traderId)
            ->where('payouts_enabled', true)
            ->whereNull('banned_at')
            ->whereNull('archived_at')
            ->first();
    }

    private function pickGateway(string $currencyCode): ?PaymentGateway
    {
        $gateways = queries()->paymentGateway()->getAllActive()
            ->filter(fn (PaymentGateway $pg) => strtolower($pg->currency->getCode()) === strtolower($currencyCode))
            ->values();

        return $gateways->isNotEmpty() ? $gateways->random() : null;
    }

    /**
     * @return array<int, string>
     */
    private function resolveCurrencies(Merchant $merchant): array
    {
        $codes = array_keys($merchant->getGeoMap());
        $supported = array_values(array_filter(
            array_map('strtolower', $codes),
            fn (string $code) => isset(DemoDataHelper::SELL_RATES[$code]) && Currency::isCurrency($code),
        ));

        return $supported !== [] ? $supported : [DemoDataHelper::DEMO_CURRENCY];
    }
}
