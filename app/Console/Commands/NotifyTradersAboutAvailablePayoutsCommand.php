<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NotificationEvent;
use App\Jobs\SendNotificationJob;
use App\Models\NotificationRule;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Notification\Events\PayoutsAvailableNotificationEvent;
use App\Services\Notification\Templates\NotificationTemplateResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class NotifyTradersAboutAvailablePayoutsCommand extends Command
{
    private const CHUNK_SIZE = 500;

    private const COOLDOWN_MINUTES = 30;

    private const STATE_TTL_DAYS = 7;

    private const STATE_EMPTY = 'empty';

    private const STATE_AVAILABLE = 'available';

    protected $signature = 'app:notify-traders-about-available-payouts';

    protected $description = 'Отправляет трейдерам Telegram-уведомления о появлении новых доступных выплат в общем стакане.';

    public function handle(NotificationTemplateResolver $templateResolver): int
    {
        $processedKeys = [];
        $checked = 0;
        $sent = 0;

        $this->rulesQuery()
            ->chunkById(self::CHUNK_SIZE, function ($rules) use (&$processedKeys, &$checked, &$sent, $templateResolver): void {
                foreach ($rules as $rule) {
                    $trader = $rule->user;
                    $currency = $rule->currency;

                    if (! $trader instanceof User || ! $currency instanceof Currency) {
                        continue;
                    }

                    $key = $this->stateKey($trader->id, $currency->getCode());

                    if (isset($processedKeys[$key])) {
                        continue;
                    }

                    $processedKeys[$key] = true;

                    if (Cache::has($this->cooldownKey($trader->id, $currency->getCode()))) {
                        continue;
                    }

                    $availableCount = queries()->payout()->countStackForTrader($trader, $currency->getCode());
                    $checked++;

                    if (Cache::pull($this->syncAfterCooldownKey($trader->id, $currency->getCode()), false)) {
                        $this->storeState($trader->id, $currency->getCode(), $availableCount);

                        continue;
                    }

                    $previousState = Cache::get($key, self::STATE_EMPTY);

                    if ($previousState === self::STATE_EMPTY && $availableCount > 0) {
                        $this->sendNotification($templateResolver, $trader, $currency, $availableCount);
                        $this->startCooldown($trader->id, $currency->getCode());
                        $sent++;

                        continue;
                    }

                    $this->storeState($trader->id, $currency->getCode(), $availableCount);
                }
            });

        $this->info("Проверено правил: {$checked}. Отправлено уведомлений: {$sent}.");

        return self::SUCCESS;
    }

    private function rulesQuery(): Builder
    {
        return NotificationRule::query()
            ->where('event', NotificationEvent::PAYOUTS_AVAILABLE->value)
            ->where('enabled', true)
            ->whereNotNull('currency')
            ->whereHas('user', function (Builder $query): void {
                $query
                    ->role('Trader')
                    ->whereNull('banned_at')
                    ->whereNull('archived_at')
                    ->where('payouts_enabled', true)
                    ->whereHas('telegramAccount', function (Builder $telegramQuery): void {
                        $telegramQuery
                            ->where('is_active', true)
                            ->whereNotNull('chat_id');
                    });
            })
            ->with([
                'user:id,email,payouts_enabled,banned_at,archived_at',
            ])
            ->orderBy('id');
    }

    private function sendNotification(
        NotificationTemplateResolver $templateResolver,
        User $trader,
        Currency $currency,
        int $availableCount
    ): void {
        $content = $templateResolver->resolve(
            new PayoutsAvailableNotificationEvent($trader, $currency, $availableCount)
        );

        SendNotificationJob::dispatch(
            $trader->id,
            $content->title,
            $content->body
        )->onQueue('notifications');
    }

    private function storeState(int $traderId, string $currency, int $availableCount): void
    {
        Cache::put(
            $this->stateKey($traderId, $currency),
            $availableCount > 0 ? self::STATE_AVAILABLE : self::STATE_EMPTY,
            now()->addDays(self::STATE_TTL_DAYS)
        );
    }

    private function startCooldown(int $traderId, string $currency): void
    {
        Cache::put(
            $this->cooldownKey($traderId, $currency),
            true,
            now()->addMinutes(self::COOLDOWN_MINUTES)
        );

        Cache::put(
            $this->syncAfterCooldownKey($traderId, $currency),
            true,
            now()->addMinutes(self::COOLDOWN_MINUTES + 5)
        );
    }

    private function stateKey(int $traderId, string $currency): string
    {
        return "notifications:payouts-available:state:{$traderId}:".strtolower($currency);
    }

    private function cooldownKey(int $traderId, string $currency): string
    {
        return "notifications:payouts-available:cooldown:{$traderId}:".strtolower($currency);
    }

    private function syncAfterCooldownKey(int $traderId, string $currency): string
    {
        return "notifications:payouts-available:sync-after-cooldown:{$traderId}:".strtolower($currency);
    }
}
