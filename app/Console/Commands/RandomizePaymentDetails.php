<?php

namespace App\Console\Commands;

use App\Models\PaymentDetail;
use Database\Factories\PaymentDetailDataFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RandomizePaymentDetails extends Command
{
    /**
     * Имя и сигнатура консольной команды
     *
     * @var string
     */
    protected $signature = 'app:randomize-payment-details 
                           {--batch=100 : Размер пакета для обработки}
                           {--memory-limit=512M : Лимит памяти для выполнения команды}';

    /**
     * Описание консольной команды
     *
     * @var string
     */
    protected $description = 'Заменяет названия и реквизиты в платежных деталях на случайные значения';

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
        
        // Получаем общее количество платежных деталей для обновления
        $totalCount = PaymentDetail::count();
        
        if ($totalCount === 0) {
            $this->error('Платежных деталей не найдено');
            return Command::FAILURE;
        }

        $this->info("Найдено {$totalCount} платежных деталей для обновления");
        $this->info("Размер пакета: {$batchSize}");
        $this->info("Лимит памяти: {$memoryLimit}");
        
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();
        
        // Обрабатываем по частям, используя курсор вместо chunk
        // для меньшего потребления памяти
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
                $ids = DB::table('payment_details')
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
                
                // Получаем записи платежных деталей по ID
                $details = PaymentDetail::whereIn('id', $ids)->get();
                
                foreach ($details as $detail) {
                    // Получаем случайные данные
                    $detailData = PaymentDetailDataFactory::getRandomPaymentDetailData($detail->detail_type);
                    
                    // Обновляем информацию о платежных деталях
                    $detail->name = $detailData['name'];
                    $detail->detail = $detailData['detail'];
                    $detail->initials = $detailData['initials'];
                    $detail->save();
                    
                    $processedCount++;
                    $bar->advance();
                }
                
                // Освобождаем память
                unset($details);
                
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
        $this->info("Обработано {$processedCount} из {$totalCount} платежных деталей");
        
        if ($processedCount === $totalCount) {
            $this->info('Все платежные детали успешно обновлены!');
            return Command::SUCCESS;
        } else {
            $this->warn("Обновлено только {$processedCount} из {$totalCount} платежных деталей");
            return Command::FAILURE;
        }
    }
} 