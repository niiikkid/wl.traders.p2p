<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MigrateApkTokens extends Command
{
    protected $signature = 'apk:migrate-tokens';
    protected $description = 'Мигрирует старые apk_access_token в новую систему UserDevice';

    public function handle()
    {
        $this->info('Начинаем миграцию токенов...');

        $users = User::whereNotNull('apk_access_token')->get();
        $this->info("Найдено пользователей с токенами: {$users->count()}");

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            // Проверяем, не существует ли уже устройство с этим токеном
            $existingDevice = UserDevice::where('token', $user->apk_access_token)->first();

            if (!$existingDevice) {
                // Создаем новое устройство
                UserDevice::create([
                    'user_id' => $user->id,
                    'name' => 'Legacy Device',
                    'token' => $user->apk_access_token,
                    'android_id' => 'legacy_' . Str::random(16),
                    'device_model' => 'Legacy Device',
                    'android_version' => 'Unknown',
                    'manufacturer' => 'Unknown',
                    'brand' => 'Unknown',
                    'connected_at' => now(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Миграция завершена успешно!');
    }
} 