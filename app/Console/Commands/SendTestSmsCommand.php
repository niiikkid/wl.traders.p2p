<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\UserDevice;

class SendTestSmsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-test-sms {--device-id=} {--sender=} {--message=} {--type=sms}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Отправляет тестовое SMS/PUSH сообщение в API приложения для существующего подключенного девайса';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $message = $this->option('message') ?? 'счёт7650 20:33 перевод по сбп из ozon банк +15р от павел н. баланс: 15.14р';
        $type = $this->option('type') ?: 'sms';
        $sender = $this->option('sender');

        // Находим подключённый девайс
        $deviceId = (int) ($this->option('device-id') ?? 0);
        if ($deviceId > 0) {
            $device = UserDevice::query()->whereNotNull('connected_at')->find($deviceId);
        } else {
            $device = UserDevice::query()->whereNotNull('connected_at')->orderByDesc('id')->first();
        }

        if (! $device) {
            $this->error('Подключённый девайс не найден.');
            return Command::FAILURE;
        }

        // Подбираем sender если не задан
        if (! $sender) {
            $activeGateways = queries()->paymentGateway()->getAllActive();
            $gateway = $activeGateways->first(fn ($pg) => is_array($pg->sms_senders) && count($pg->sms_senders) > 0);
            $sender = $gateway && $gateway->sms_senders ? ($gateway->sms_senders[0] ?? 'TESTBANK') : 'TESTBANK';
        }

        $apiBase = rtrim(config('app.url'), '/');
        $smsUrl = $apiBase . '/api/app/sms';

        $payload = [
            'sender' => $sender,
            'message' => $message,
            'timestamp' => now()->getTimestamp(),
            'type' => $type,
        ];

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Access-Token' => $device->token,
                    'Idempotency-Key' => (string) \Illuminate\Support\Str::uuid(),
                ])
                ->post($smsUrl, $payload);

            if (! $response->ok()) {
                $this->warn('HTTP ' . $response->status() . ': ' . $response->body());
            } else {
                $this->info('Тестовое сообщение отправлено. Проверьте SMS-логи.');
            }
        } catch (\Throwable $e) {
            $this->error('Ошибка отправки: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}


