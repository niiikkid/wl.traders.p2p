<?php

namespace App\Jobs\TestData;

use App\Enums\UserActivityAction;
use App\Models\CallbackLog;
use App\Models\MerchantApiRequestLog;
use App\Models\Order;
use App\Models\Payout\Payout;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Support\TestData\DemoDataHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Завершающий этап генерации демо-данных: синтезирует журналы, которые
 * привязаны к уже созданным заказам/выплатам (callback-логи, логи API мерчанта,
 * логи активности), и пересчитывает агрегированную статистику API.
 *
 * Джоба ставится в очередь последней; на однопоточной очереди test-data она
 * гарантированно выполнится после всех задач генерации заказов и выплат.
 */
class FinalizeDemoDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        private readonly int $days = 30,
    ) {
        $this->onQueue('test-data');
    }

    public function handle(): void
    {
        $this->seedOrderLogs();
        $this->seedPayoutLogs();
        $this->rebuildApiStatistics();
    }

    private function seedOrderLogs(): void
    {
        $actorIds = User::query()->role(['Super Admin', 'Support'])->pluck('id')->all();

        Order::query()
            ->whereIn('status', ['success', 'fail'])
            ->with(['merchant', 'paymentGateway', 'paymentDetail'])
            ->latest('id')
            ->limit(600)
            ->get()
            ->each(function (Order $order) use ($actorIds) {
                try {
                    $this->createMerchantApiLogForOrder($order);
                    $this->createCallbackLogForOrder($order);
                    $this->maybeCreateActivityLog($order, 'order', $order->uuid, $actorIds);
                } catch (\Throwable $e) {
                    Log::warning('[demo-data] order log skipped: '.$e->getMessage());
                }
            });
    }

    private function seedPayoutLogs(): void
    {
        $actorIds = User::query()->role(['Super Admin', 'Support'])->pluck('id')->all();

        Payout::query()
            ->with(['merchant', 'paymentGateway'])
            ->latest('id')
            ->limit(400)
            ->get()
            ->each(function (Payout $payout) use ($actorIds) {
                try {
                    $this->createMerchantApiLogForPayout($payout);
                    $this->createCallbackLogForPayout($payout);
                    $this->maybeCreateActivityLog($payout, 'payout', $payout->uuid, $actorIds);
                } catch (\Throwable $e) {
                    Log::warning('[demo-data] payout log skipped: '.$e->getMessage());
                }
            });
    }

    private function createMerchantApiLogForOrder(Order $order): void
    {
        if (! $order->is_h2h) {
            return;
        }

        $success = $order->status->value === 'success';
        $createdAt = $order->created_at;

        MerchantApiRequestLog::create([
            'request_id' => (string) Str::uuid(),
            'request_type' => MerchantApiRequestLog::TYPE_ORDER,
            'external_id' => $order->external_id,
            'amount' => (string) $order->base_amount->toUnits(),
            'currency' => $order->currency?->getCode(),
            'payment_gateway' => $order->paymentGateway?->name,
            'payment_detail_type' => $order->paymentDetail?->detail_type?->value,
            'request_data' => [
                'external_id' => $order->external_id,
                'amount' => $order->base_amount->toBeauty(),
                'currency' => $order->currency?->getCode(),
                'payment_detail_type' => $order->paymentDetail?->detail_type?->value,
            ],
            'response_data' => [
                'success' => true,
                'data' => ['order_id' => $order->uuid],
            ],
            'ip_address' => DemoDataHelper::ip(),
            'user_agent' => 'MerchantApiClient/1.0',
            'execution_time' => round(random_int(35, 850) + (random_int(0, 99) / 100), 2),
            'is_successful' => true,
            'merchant_id' => $order->merchant_id,
            'order_id' => $order->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function createMerchantApiLogForPayout(Payout $payout): void
    {
        $createdAt = $payout->created_at;

        MerchantApiRequestLog::create([
            'request_id' => (string) Str::uuid(),
            'request_type' => MerchantApiRequestLog::TYPE_PAYOUT,
            'external_id' => $payout->external_id,
            'amount' => (string) $payout->amount_fiat->toUnits(),
            'currency' => $payout->amount_fiat->getCurrency()->getCode(),
            'payment_gateway' => $payout->paymentGateway?->name,
            'payment_detail_type' => $payout->payout_method_type?->value,
            'request_data' => [
                'external_id' => $payout->external_id,
                'amount' => $payout->amount_fiat->toBeauty(),
                'currency' => $payout->amount_fiat->getCurrency()->getCode(),
                'payout_method_type' => $payout->payout_method_type?->value,
            ],
            'response_data' => [
                'success' => true,
                'data' => ['payout_id' => $payout->uuid],
            ],
            'ip_address' => DemoDataHelper::ip(),
            'user_agent' => 'MerchantApiClient/1.0',
            'execution_time' => round(random_int(35, 850) + (random_int(0, 99) / 100), 2),
            'is_successful' => true,
            'merchant_id' => $payout->merchant_id,
            'payout_id' => $payout->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function createCallbackLogForOrder(Order $order): void
    {
        $success = random_int(1, 100) <= 92;
        $createdAt = $order->finished_at ?? $order->created_at;

        $order->callbackLogs()->create([
            'type' => CallbackLog::TYPE_ORDER,
            'url' => 'https://'.($order->merchant?->domain ?: 'shop.example.com').'/callbacks/order',
            'request_data' => [
                'order_id' => $order->uuid,
                'external_id' => $order->external_id,
                'status' => $order->status->value,
                'amount' => $order->amount->toBeauty(),
            ],
            'response_data' => $success ? ['ok' => true] : ['error' => 'timeout'],
            'status_code' => $success ? 200 : 504,
            'is_success' => $success,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function createCallbackLogForPayout(Payout $payout): void
    {
        $success = random_int(1, 100) <= 92;
        $createdAt = $payout->completed_at ?? $payout->created_at;

        $payout->callbackLogs()->create([
            'type' => CallbackLog::TYPE_PAYOUT,
            'url' => 'https://'.($payout->merchant?->domain ?: 'shop.example.com').'/callbacks/payout',
            'request_data' => [
                'payout_id' => $payout->uuid,
                'external_id' => $payout->external_id,
                'status' => $payout->status->value,
                'amount' => $payout->amount_fiat->toBeauty(),
            ],
            'response_data' => $success ? ['ok' => true] : ['error' => 'timeout'],
            'status_code' => $success ? 200 : 504,
            'is_success' => $success,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    /**
     * @param  array<int, int>  $actorIds
     */
    private function maybeCreateActivityLog(object $subject, string $subjectType, ?string $subjectUuid, array $actorIds): void
    {
        if ($actorIds === [] || random_int(1, 100) > 25) {
            return;
        }

        $action = random_int(0, 1) === 0 ? UserActivityAction::Created : UserActivityAction::Updated;
        $ua = DemoDataHelper::userAgents();
        $createdAt = $subject->created_at ?? now();

        UserActivityLog::create([
            'actor_user_id' => $actorIds[array_rand($actorIds)],
            'impersonator_user_id' => null,
            'actor_role' => 'Super Admin',
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subject->id,
            'subject_uuid' => $subjectUuid,
            'route_name' => $subjectType === 'order' ? 'admin.orders.index' : 'admin.payouts.index',
            'ip_address' => DemoDataHelper::ip(),
            'user_agent' => $ua[array_rand($ua)]['ua'],
            'changes' => [],
            'meta' => ['source' => 'demo-data'],
            'created_at' => $createdAt,
        ]);
    }

    private function rebuildApiStatistics(): void
    {
        try {
            services()->merchantApiStatistics()->updateStatistics(
                now()->subDays($this->days + 1)->startOfDay(),
                now()->endOfDay(),
            );
        } catch (\Throwable $e) {
            Log::warning('[demo-data] api statistics rebuild failed: '.$e->getMessage());
        }
    }
}
