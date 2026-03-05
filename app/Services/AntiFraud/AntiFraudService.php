<?php

namespace App\Services\AntiFraud;

use App\Contracts\AntiFraudServiceContract;
use App\Enums\OrderStatus;
use App\Exceptions\AntiFraudException;
use App\Models\AntiFraudSetting;
use App\Models\AntiFraudLog;
use App\Models\Merchant;
use App\Models\MerchantClient;
use App\Models\Order;
use Illuminate\Support\Carbon;

class AntiFraudService implements AntiFraudServiceContract
{
    public function check(Merchant $merchant, ?string $clientId): ?MerchantClient
    {
        $settings = services()->antiFraudSetting()->getForMerchant($merchant->id);

        if (! $settings || ! $settings->enabled) {
            return null;
        }

        $clientId = trim((string) $clientId);
        if ($clientId === '') {
            $exception = AntiFraudException::clientIdRequired();
            $this->logDecision($merchant, null, null, 'deny', $exception->getMessage(), [
                'traffic_type' => 'unknown',
            ]);
            throw $exception;
        }

        $client = MerchantClient::query()->firstOrCreate(
            ['merchant_id' => $merchant->id, 'client_id' => $clientId]
        );

        if ($client->blocked_until && $client->blocked_until->isFuture()) {
            $exception = AntiFraudException::blockedUntil($client->blocked_until->toDateTimeString());
            $this->logDecision($merchant, $client, $clientId, 'deny', $exception->getMessage(), [
                'traffic_type' => $this->resolveTrafficType($client),
            ]);
            throw $exception;
        }

        $trafficType = $this->resolveTrafficType($client);

        try {
            $this->checkMaxPending($client, $settings, $trafficType);
            $this->checkRateLimits($client, $settings, $trafficType);
            $this->checkFailedLimit($client, $settings, $trafficType);
        } catch (AntiFraudException $exception) {
            $this->logDecision($merchant, $client, $clientId, 'deny', $exception->getMessage(), [
                'traffic_type' => $trafficType,
            ]);
            throw $exception;
        }

        $this->logDecision($merchant, $client, $clientId, 'allow', null, [
            'traffic_type' => $trafficType,
        ]);

        return $client;
    }

    private function resolveTrafficType(MerchantClient $client): string
    {
        $hasSuccess = Order::query()
            ->where('merchant_client_id', $client->id)
            ->where('status', OrderStatus::SUCCESS)
            ->exists();

        return $hasSuccess ? 'secondary' : 'primary';
    }

    private function checkMaxPending(
        MerchantClient $client,
        AntiFraudSetting $settings,
        string $trafficType
    ): void {
        if ($trafficType === 'secondary' && $settings->secondary_enabled === false) {
            return;
        }

        $limit = $trafficType === 'primary'
            ? $settings->primary_max_pending
            : $settings->secondary_max_pending;

        if (! $limit) {
            return;
        }

        $pendingCount = Order::query()
            ->where('merchant_client_id', $client->id)
            ->where('status', OrderStatus::PENDING)
            ->count();

        if ($pendingCount >= $limit) {
            throw AntiFraudException::maxPendingExceeded($pendingCount, $limit);
        }
    }

    private function checkRateLimits(
        MerchantClient $client,
        AntiFraudSetting $settings,
        string $trafficType
    ): void {
        if ($trafficType === 'secondary' && $settings->secondary_enabled === false) {
            return;
        }

        $limits = $trafficType === 'primary'
            ? ($settings->primary_rate_limits ?? [])
            : ($settings->secondary_rate_limits ?? []);

        if (! $limits) {
            return;
        }

        foreach ($limits as $limit) {
            $count = (int) ($limit['count'] ?? 0);
            $minutes = (int) ($limit['minutes'] ?? 0);

            if ($count <= 0 || $minutes <= 0) {
                continue;
            }

            $since = Carbon::now()->subMinutes($minutes);
            $createdCount = Order::query()
                ->where('merchant_client_id', $client->id)
                ->where('created_at', '>=', $since)
                ->count();

            if ($createdCount >= $count) {
                throw AntiFraudException::rateLimitExceeded($createdCount, $count, $minutes);
            }
        }
    }

    private function checkFailedLimit(
        MerchantClient $client,
        AntiFraudSetting $settings,
        string $trafficType
    ): void {
        if ($trafficType === 'secondary' && $settings->secondary_enabled === false) {
            return;
        }

        $limit = $trafficType === 'primary'
            ? $settings->primary_failed_limit
            : $settings->secondary_failed_limit;

        if (! $limit) {
            return;
        }

        $recentOrdersQuery = Order::query()
            ->where('merchant_client_id', $client->id)
            ->whereIn('status', [OrderStatus::SUCCESS, OrderStatus::FAIL])
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($client->blocked_until) {
            $recentOrdersQuery->where('created_at', '>=', $client->blocked_until);
        }

        $recentOrders = $recentOrdersQuery->get();
        $recentCount = $recentOrders->count();
        if ($recentCount < $limit) {
            return;
        }

        $allFailed = $recentOrders->every(fn (Order $order) => $order->status->equals(OrderStatus::FAIL));
        if (! $allFailed) {
            return;
        }

        $blockDays = $trafficType === 'primary'
            ? $settings->primary_block_days
            : $settings->secondary_block_days;

        if ($blockDays && $blockDays > 0) {
            $client->blocked_until = now()->addDays($blockDays);
            $client->save();
        }

        throw AntiFraudException::failedLimitExceeded($recentCount, $limit);
    }

    private function logDecision(
        Merchant $merchant,
        ?MerchantClient $client,
        ?string $clientId,
        string $decision,
        ?string $message = null,
        array $meta = []
    ): void {
        AntiFraudLog::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_client_id' => $client?->id,
            'client_id' => $clientId,
            'decision' => $decision,
            'message' => $message,
            'meta' => $meta ?: null,
        ]);
    }
}
