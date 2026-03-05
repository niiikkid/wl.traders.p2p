<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetAllPasswordsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-all-passwords {password? : Новый пароль для всех пользователей (по умолчанию: "password")}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Сбросить пароли всех пользователей на одинаковый пароль';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $password = $this->argument('password') ?? 'password';

        $this->info("Начинаю сброс паролей для всех пользователей...");
        $this->info("Новый пароль: {$password}");

        /*// Подтверждение действия
        if (!$this->confirm('Вы уверены, что хотите сбросить пароли ВСЕМ пользователям? Это действие необратимо!')) {
            $this->error('Операция отменена.');
            return 1;
        }*/

        // Получаем общее количество пользователей
        $totalUsers = User::count();
        $this->info("Найдено пользователей: {$totalUsers}");

        if ($totalUsers === 0) {
            $this->warn('Пользователи не найдены.');
            return 0;
        }

        // Создаем прогресс-бар
        $progressBar = $this->output->createProgressBar($totalUsers);
        $progressBar->start();

        $updatedCount = 0;

        // Обновляем пароли всех пользователей
        User::chunk(100, function ($users) use ($password, $progressBar, &$updatedCount) {
            foreach ($users as $user) {
                $user->update([
                    'password' => Hash::make($password)
                ]);
                $updatedCount++;
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine();

        $this->info("✅ Успешно обновлено паролей: {$updatedCount}");
        $this->info("🔑 Новый пароль для всех пользователей: {$password}");

        return 0;
    }
}
