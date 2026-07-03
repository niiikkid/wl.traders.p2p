<?php

namespace App\Console\Commands\Dev;

use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;

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

        $this->info('Пользователь успешно создан!');
        $this->info('- Администратор (admin / password)');

        return Command::SUCCESS;
    }
}
