<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use Database\Factories\MerchantDataFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RandomizeMerchants extends Command
{
    /**
     * Имя и сигнатура консольной команды
     *
     * @var string
     */
    protected $signature = 'app:randomize-merchants 
                           {--batch=50 : Размер пакета для обработки}
                           {--memory-limit=512M : Лимит памяти для выполнения команды}';

    /**
     * Описание консольной команды
     *
     * @var string
     */
    protected $description = 'Заменяет название, описание, домен и callback URL мерчантов на случайные значения';

    /**
     * Выполнение консольной команды
     */
    public function handle(): int
    {
        // Устанавливаем лимит памяти
        $memoryLimit = $this->option('memory-limit');
        ini_set('memory_limit', $memoryLimit);
        
        // Получаем размер пакета из опций
        $batchSize = (int)$this->option('batch');
        
        // Получаем общее количество мерчантов для обновления
        $totalCount = Merchant::count();
        
        if ($totalCount === 0) {
            $this->error('Мерчантов не найдено');
            return Command::FAILURE;
        }

        $this->info("Найдено {$totalCount} мерчантов для обновления");
        $this->info("Размер пакета: {$batchSize}");
        $this->info("Лимит памяти: {$memoryLimit}");
        
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();
        
        // Обрабатываем по частям для оптимизации использования памяти
        $processedCount = 0;
        $failedCount = 0;
        
        // Используем прямые запросы к БД для экономии памяти
        $lastId = 0;
        $processing = true;
        
        while ($processing) {
            // Начинаем транзакцию для текущего пакета
            DB::beginTransaction();
            
            try {
                // Получаем текущий пакет ID записей
                $ids = DB::table('merchants')
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit($batchSize)
                    ->pluck('id');
                
                if ($ids->isEmpty()) {
                    $processing = false;
                    DB::commit();
                    continue;
                }
                
                // Обновляем последний обработанный ID
                $lastId = $ids->last();
                
                // Получаем записи мерчантов по ID
                $merchants = Merchant::whereIn('id', $ids)->get();
                
                foreach ($merchants as $merchant) {
                    // Получаем случайные данные для мерчанта
                    $merchantData = MerchantDataFactory::getRandomMerchantData();
                    
                    // Обновляем только указанные поля
                    $merchant->name = $merchantData['name'];
                    $merchant->description = $merchantData['description'];
                    $merchant->domain = $merchantData['domain'];
                    $merchant->callback_url = $merchantData['callback_url'];
                    $merchant->payout_callback_url = $merchantData['payout_callback_url'] ?? $merchantData['callback_url'];
                    $merchant->save();
                    
                    $processedCount++;
                    $bar->advance();
                }
                
                // Освобождаем память
                unset($merchants);
                
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $failedCount++;
                
                $this->newLine();
                $this->warn("Ошибка при обработке пакета: " . $e->getMessage());
                
                // Если слишком много ошибок, прерываем выполнение
                if ($failedCount > 5) {
                    $this->error('Слишком много ошибок, прерываем выполнение');
                    return Command::FAILURE;
                }
                
                // Продолжаем со следующего пакета
                $this->info("Продолжаем со следующего пакета...");
            }
            
            // Принудительно очищаем память
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
        
        $bar->finish();
        
        $this->newLine();
        $this->info("Обработано {$processedCount} из {$totalCount} мерчантов");
        
        if ($processedCount === $totalCount) {
            $this->info('Все данные мерчантов успешно обновлены!');
            return Command::SUCCESS;
        } else {
            $this->warn("Обновлено только {$processedCount} из {$totalCount} мерчантов");
            return Command::FAILURE;
        }
    }
} 