<?php

namespace App\Console\Commands\Dev;

use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class CreateUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:users:create';

    protected $aliases = ['app:create-users'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Создает пользователей с разными ролями в системе';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Начинаем создание пользователей...');

        $seeder = new UserSeeder;
        $seeder->run();

        $this->info('Пользователи успешно созданы!');
        $this->info('Созданы следующие пользователи:');
        $this->info('- Администратор (admin@example.com / password)');
        $this->info('- Трейдер (trader@example.com / password)');
        $this->info('- Мерчант (merchant@example.com / password)');

        if (Role::where('name', 'Team Leader')->exists()) {
            $this->info('- Тимлидер (teamleader@example.com / password)');
        }

        return Command::SUCCESS;
    }
}
