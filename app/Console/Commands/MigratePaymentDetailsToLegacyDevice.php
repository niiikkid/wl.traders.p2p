<?php

namespace App\Console\Commands;

use App\Models\PaymentDetail;
use App\Models\UserDevice;
use Illuminate\Console\Command;

class MigratePaymentDetailsToLegacyDevice extends Command
{
    protected $signature = 'payment-details:migrate-to-legacy-device';
    protected $description = 'Мигрирует существующие реквизиты к Legacy устройству';

    public function handle()
    {
        $this->info('Начинаем миграцию реквизитов...');

        $paymentDetails = PaymentDetail::whereNull('user_device_id')->get();
        $this->info("Найдено реквизитов для миграции: {$paymentDetails->count()}");

        $bar = $this->output->createProgressBar($paymentDetails->count());
        $bar->start();

        foreach ($paymentDetails as $paymentDetail) {
            $legacyDevice = UserDevice::where('user_id', $paymentDetail->user_id)
                ->where('android_id', 'like', 'legacy_%')
                ->first();

            if ($legacyDevice) {
                $paymentDetail->update(['user_device_id' => $legacyDevice->id]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Миграция завершена успешно!');
    }
} 