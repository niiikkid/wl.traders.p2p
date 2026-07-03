<?php

namespace App\Jobs\TestData;

use App\Enums\SmsType;
use App\Models\SmsLog;
use App\Models\UserDevice;
use App\Support\TestData\DemoDataHelper;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Наполняет журнал банковских SMS/PUSH-сообщений для одного устройства трейдера.
 */
class SeedDeviceMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        private readonly int $deviceId,
        private readonly int $count,
        private readonly int $days = 30,
    ) {
        $this->onQueue('test-data');
    }

    public function handle(): void
    {
        $device = UserDevice::query()->whereKey($this->deviceId)->first();

        if (! $device) {
            return;
        }

        // Разрешаем массовое присвоение created_at для исторического распределения.
        Model::unguard();
        try {
            $this->generate($device);
        } finally {
            Model::reguard();
        }
    }

    private function generate(UserDevice $device): void
    {

        $gateways = queries()->paymentGateway()->getAllActive()
            ->filter(fn ($pg) => is_array($pg->sms_senders) && count($pg->sms_senders) > 0)
            ->values();

        // Наиболее частые отправители на случай отсутствия шлюзов с sms_senders.
        $fallbackSenders = ['900', 'Sberbank', 'Tinkoff', 'VTB', 'AlfaBank', 'SBP'];

        for ($i = 0; $i < $this->count; $i++) {
            if ($gateways->isNotEmpty()) {
                $gateway = $gateways->random();
                $sender = $gateway->sms_senders[array_rand($gateway->sms_senders)];
                $bank = $gateway->name;
            } else {
                $sender = $fallbackSenders[array_rand($fallbackSenders)];
                $bank = DemoDataHelper::bankName();
            }

            $amount = DemoDataHelper::realisticFiatAmount(DemoDataHelper::DEMO_CURRENCY);
            $last4 = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $createdAt = Carbon::now()
                ->subDays(random_int(0, max(0, $this->days)))
                ->setTime(random_int(0, 23), random_int(0, 59), random_int(0, 59));

            SmsLog::create([
                'sender' => (string) $sender,
                'message' => DemoDataHelper::bankMessage($amount, $last4),
                'parsing_result' => [
                    'operation_type' => 'in',
                    'amount' => $amount,
                    'card' => $last4,
                    'balance' => random_int(5_000, 900_000),
                    'bank' => $bank,
                ],
                'timestamp' => (string) $createdAt->getTimestamp(),
                'type' => random_int(0, 1) === 0 ? SmsType::SMS : SmsType::PUSH,
                'user_id' => $device->user_id,
                'user_device_id' => $device->id,
                'order_id' => null,
                // ~10% сообщений отклонены (нераспознаны/дубликаты).
                'rejected_at' => random_int(1, 100) <= 10 ? $createdAt : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
