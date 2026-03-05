<?php

namespace App\Console\Commands;

use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;

class CreateUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-users';

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

        $seeder = new UserSeeder();
        $seeder->run();

        $this->info('Пользователи успешно созданы!');
        $this->info('Созданы следующие пользователи:');
        $this->info('- Администратор (admin@example.com / password)');
        $this->info('- Трейдер (trader@example.com / password)');
        $this->info('- Мерчант (merchant@example.com / password)');
        
        if (\Spatie\Permission\Models\Role::where('name', 'Team Leader')->exists()) {
            $this->info('- Тимлидер (teamleader@example.com / password)');
        }

        return Command::SUCCESS;
    }
} 