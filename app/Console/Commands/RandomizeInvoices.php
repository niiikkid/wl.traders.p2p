<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Database\Factories\InvoiceDataFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RandomizeInvoices extends Command
{
    /**
     * Имя и сигнатура консольной команды
     *
     * @var string
     */
    protected $signature = 'app:randomize-invoices 
                           {--batch=50 : Размер пакета для обработки}
                           {--memory-limit=512M : Лимит памяти для выполнения команды}';

    /**
     * Описание консольной команды
     *
     * @var string
     */
    protected $description = 'Заменяет external_id, address, tx_hash и transaction_id инвойсов на случайные значения (только для заполненных полей)';

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
        
        // Поля для рандомизации
        $fields = [
            'external_id',
            'address',
            'tx_hash',
            'transaction_id'
        ];
        
        // Подсчитываем количество инвойсов для каждого поля
        $countsPerField = [];
        $totalRecords = 0;
        
        foreach ($fields as $field) {
            $count = DB::table('invoices')
                ->whereNotNull($field)
                ->where($field, '!=', '')
                ->count();
            
            $countsPerField[$field] = $count;
            $totalRecords += $count;
            
            $this->info("Найдено {$count} инвойсов с непустым полем {$field}");
        }
        
        if ($totalRecords === 0) {
            $this->error('Не найдено инвойсов с непустыми полями для обновления');
            return Command::FAILURE;
        }

        $this->info("Всего будет обновлено {$totalRecords} полей инвойсов");
        $this->info("Размер пакета: {$batchSize}");
        $this->info("Лимит памяти: {$memoryLimit}");
        
        $bar = $this->output->createProgressBar($totalRecords);
        $bar->start();
        
        $processedCount = 0;
        $failedCount = 0;
        
        // Обрабатываем каждое поле отдельно
        foreach ($fields as $field) {
            if ($countsPerField[$field] === 0) {
                continue;
            }
            
            $this->info("\nОбрабатываем поле: {$field}");
            
            // Используем прямые запросы к БД для экономии памяти
            $lastId = 0;
            $processing = true;
            
            while ($processing) {
                // Начинаем транзакцию для текущего пакета
                DB::beginTransaction();
                
                try {
                    // Получаем текущий пакет ID записей с непустым значением поля
                    $ids = DB::table('invoices')
                        ->where('id', '>', $lastId)
                        ->whereNotNull($field)
                        ->where($field, '!=', '')
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
                    
                    // Получаем записи инвойсов по ID
                    $invoices = Invoice::whereIn('id', $ids)->get();
                    
                    foreach ($invoices as $invoice) {
                        // Генерируем только нужное поле
                        $invoiceData = null;
                        
                        switch ($field) {
                            case 'external_id':
                                $value = InvoiceDataFactory::generateExternalId();
                                break;
                                
                            case 'address':
                                $value = InvoiceDataFactory::generateAddress($invoice->network);
                                break;
                                
                            case 'tx_hash':
                                $value = InvoiceDataFactory::generateTxHash($invoice->network);
                                break;
                                
                            case 'transaction_id':
                                $value = InvoiceDataFactory::generateTransactionId();
                                break;
                                
                            default:
                                continue 2; // Пропустить неизвестное поле и перейти к следующей итерации цикла while
                        }
                        
                        // Обновляем только одно поле
                        $invoice->$field = $value;
                        $invoice->save();
                        
                        $processedCount++;
                        $bar->advance();
                    }
                    
                    // Освобождаем память
                    unset($invoices);
                    
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
        }
        
        $bar->finish();
        
        $this->newLine();
        $this->info("Обработано {$processedCount} полей инвойсов из {$totalRecords}");
        
        if ($processedCount === $totalRecords) {
            $this->info('Все непустые поля инвойсов успешно обновлены!');
            return Command::SUCCESS;
        } else {
            $this->warn("Обновлено только {$processedCount} из {$totalRecords} полей инвойсов");
            return Command::FAILURE;
        }
    }
} 