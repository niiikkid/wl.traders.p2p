<?php

namespace App\Jobs\TestData;

use App\DTO\Order\CreateOrderDTO;
use App\Enums\DetailType;
use App\Enums\DisputeCancelReasonCode;
use App\Enums\OrderSubStatus;
use App\Models\Merchant;
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
 * Создаёт и завершает партию реалистичных заказов (pay-in) для одного мерчанта,
 * распределяя их по последним $days дням и открывая споры по части отменённых.
 *
 * Джоба намеренно небольшая (одна партия), чтобы генерация большого объёма
 * данных разбивалась на множество изолированных задач и не падала на таймауте.
 */
class SeedMerchantOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        private readonly int $merchantId,
        private readonly int $count,
        private readonly int $days = 30,
    ) {
        $this->onQueue('test-data');
    }

    public function handle(): void
    {
        // Курсы могли протухнуть в кэше к моменту обработки — гарантируем их наличие.
        DemoDataHelper::seedMarketPrices();

        $merchant = Merchant::query()
            ->with('user')
            ->whereKey($this->merchantId)
            ->whereNotNull('validated_at')
            ->whereNull('banned_at')
            ->where('active', true)
            ->first();

        if (! $merchant) {
            return;
        }

        $currencies = $this->resolveCurrencies($merchant);

        for ($i = 0; $i < $this->count; $i++) {
            try {
                $this->createOne($merchant, $currencies);
            } catch (\Throwable $e) {
                Log::warning('[demo-data] order creation skipped: '.$e->getMessage());
            }
        }
    }

    /**
     * @param  array<int, string>  $currencies
     */
    private function createOne(Merchant $merchant, array $currencies): void
    {
        $currencyCode = $currencies[array_rand($currencies)];
        $detailType = random_int(1, 100) <= 70
            ? DetailType::CARD
            : DetailType::PHONE;

        $amount = DemoDataHelper::realisticFiatAmount($currencyCode);

        $dto = new CreateOrderDTO(
            amount: Money::fromPrecision((string) $amount, $currencyCode),
            merchant: $merchant,
            h2h: random_int(0, 1) === 1,
            externalID: 'demo-'.Str::uuid()->toString(),
            callbackURL: null,
            paymentDetailType: $detailType,
        );

        $order = services()->order()->create($dto);

        $roll = random_int(1, 100);
        $createdAt = Carbon::now()
            ->subDays(random_int(0, max(0, $this->days)))
            ->setTime(random_int(6, 23), random_int(0, 59), random_int(0, 59));

        if ($roll <= 68) {
            // Успешно оплачен.
            $subStatus = random_int(0, 1) === 0
                ? OrderSubStatus::ACCEPTED
                : OrderSubStatus::SUCCESSFULLY_PAID;
            services()->order()->finishOrderAsSuccessful($order->id, $subStatus);
            $this->backdateOrder($order->id, $createdAt);
        } elseif ($roll <= 86) {
            // Отменён вручную — часть таких сделок уходит в спор.
            services()->order()->finishOrderAsFailed($order->id, OrderSubStatus::CANCELED);
            $this->maybeOpenDispute($order->id, $createdAt);
            // Ре-бэкдейт после спора: сервисы спора выставляют finished_at = now().
            $this->backdateOrder($order->id, $createdAt);
        } elseif ($roll <= 94) {
            // Истёк срок оплаты.
            services()->order()->finishOrderAsFailed($order->id, OrderSubStatus::EXPIRED);
            $this->backdateOrder($order->id, $createdAt);
        } else {
            // Оставляем в ожидании оплаты (свежая активная сделка).
            $this->backdatePending($order->id);
        }
    }

    private function maybeOpenDispute(int $orderId, Carbon $createdAt): void
    {
        // Спор открывается примерно по 40% отменённых сделок.
        if (random_int(1, 100) > 40) {
            return;
        }

        try {
            $dispute = services()->dispute()->create(
                $orderId,
                DemoDataHelper::pngUploadedFile('receipt.png'),
            );
        } catch (\Throwable $e) {
            return;
        }

        $disputeCreatedAt = (clone $createdAt)->addMinutes(random_int(30, 600));
        DB::table('disputes')->where('id', $dispute->id)->update([
            'created_at' => $disputeCreatedAt,
            'updated_at' => $disputeCreatedAt,
        ]);

        $resolution = random_int(0, 2);

        try {
            if ($resolution === 1) {
                services()->dispute()->accept($dispute->id);
            } elseif ($resolution === 2) {
                services()->dispute()->cancel(
                    $dispute->id,
                    DisputeCancelReasonCode::OTHER,
                    'Платёж не найден в выписке банка',
                    DemoDataHelper::pngUploadedFile('bank_statement.png'),
                );
            }
            // resolution === 0 — оставляем спор в статусе PENDING.
        } catch (\Throwable $e) {
            // Игнорируем частные ошибки резолюции спора.
        }
    }

    /**
     * Проставляет исторические даты заказу. finished_at выставляется только
     * если заказ действительно завершён (не находится в ожидании/споре).
     */
    private function backdateOrder(int $orderId, Carbon $createdAt): void
    {
        $status = DB::table('orders')->where('id', $orderId)->value('status');
        $expiresAt = (clone $createdAt)->addMinutes(15);

        $update = [
            'created_at' => $createdAt,
            'rate_fixed_at' => $createdAt,
            'expires_at' => $expiresAt,
            'updated_at' => $createdAt,
        ];

        if (in_array($status, ['success', 'fail'], true)) {
            $finishedAt = (clone $createdAt)->addMinutes(random_int(2, 15));
            $update['finished_at'] = $finishedAt;
            $update['updated_at'] = $finishedAt;
        }

        DB::table('orders')->where('id', $orderId)->update($update);
    }

    private function backdatePending(int $orderId): void
    {
        $createdAt = Carbon::now()->subMinutes(random_int(1, 240));
        $expiresAt = (clone $createdAt)->addMinutes(15);

        DB::table('orders')->where('id', $orderId)->update([
            'created_at' => $createdAt,
            'rate_fixed_at' => $createdAt,
            'expires_at' => $expiresAt,
            'updated_at' => $createdAt,
        ]);
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
