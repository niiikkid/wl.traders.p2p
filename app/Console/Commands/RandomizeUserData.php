<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Factories\UserRandomizerFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RandomizeUserData extends Command
{
    /**
     * Имя и сигнатура консольной команды
     *
     * @var string
     */
    protected $signature = 'app:randomize-user-data {--batch=100 : Размер пакета для обработки}';

    /**
     * Описание консольной команды
     *
     * @var string
     */
    protected $description = 'Заменяет имена и email-адреса пользователей на новые уникальные тестовые значения';

    /**
     * Выполнение консольной команды
     */
    public function handle(): int
    {
        // Получаем размер пакета из опций
        $batchSize = (int)$this->option('batch');
        
        // Получаем количество пользователей для обновления
        $usersCount = User::count();
        
        if ($usersCount === 0) {
            $this->error('Пользователей не найдено');
            return Command::FAILURE;
        }

        $this->info("Найдено {$usersCount} пользователей для обновления");
        $this->info("Размер пакета: {$batchSize}");
        
        $bar = $this->output->createProgressBar($usersCount);
        $bar->start();
        
        // Используем транзакцию для атомарного обновления
        DB::beginTransaction();
        
        try {
            // Обрабатываем пользователей пакетами для оптимизации производительности
            User::query()->chunkById($batchSize, function ($users) use ($bar) {
                foreach ($users as $user) {
                    // Получаем случайные данные из нашей фабрики
                    $randomData = UserRandomizerFactory::getRandomUserData();
                    
                    // Обновляем пользователя
                    $user->name = $randomData['name'];
                    $user->email = $randomData['email'];
                    $user->save();
                    
                    $bar->advance();
                }
            });
            
            DB::commit();
            $bar->finish();
            
            $this->newLine();
            $this->info('Все имена и email-адреса пользователей успешно обновлены!');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            
            $this->newLine();
            $this->error('Произошла ошибка при обновлении данных: ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
} 