<?php

namespace App\Console\Commands\Users;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetAdminPasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:passwords:reset-admin {login : Логин администратора (email)} {password : Новый пароль}';

    protected $aliases = ['app:reset-admin-password'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Сбросить пароль выбранному администратору (Super Admin)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $login = strtolower((string) $this->argument('login'));
        $password = (string) $this->argument('password');

        $user = User::query()
            ->where('email', $login)
            ->role('Super Admin')
            ->first();

        if ($user === null) {
            $this->error("Администратор с логином «{$login}» не найден.");

            return self::FAILURE;
        }

        $user->update([
            'password' => Hash::make($password),
        ]);

        $this->info("✅ Пароль успешно обновлён для администратора: {$user->email}");

        return self::SUCCESS;
    }
}
