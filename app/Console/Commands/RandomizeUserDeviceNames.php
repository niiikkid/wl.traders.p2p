<?php

namespace App\Console\Commands;

use App\Models\UserDevice;
use Database\Factories\DeviceDataFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RandomizeUserDeviceNames extends Command
{
    /**
     * Имя и сигнатура консольной команды
     *
     * @var string
     */
    protected $signature = 'app:randomize-device-names {--batch=100 : Размер пакета для обработки}';

    /**
     * Описание консольной команды
     *
     * @var string
     */
    protected $description = 'Заменяет названия устройств пользователей на случайные значения';

    /**
     * Выполнение консольной команды
     */
    public function handle(): int
    {
        // Получаем размер пакета из опций
        $batchSize = (int)$this->option('batch');
        
        // Получаем количество устройств для обновления
        $devicesCount = UserDevice::count();
        
        if ($devicesCount === 0) {
            $this->error('Устройств не найдено');
            return Command::FAILURE;
        }

        $this->info("Найдено {$devicesCount} устройств для обновления");
        $this->info("Размер пакета: {$batchSize}");
        
        $bar = $this->output->createProgressBar($devicesCount);
        $bar->start();
        
        // Используем транзакцию для атомарного обновления
        DB::beginTransaction();
        
        try {
            // Обрабатываем устройства пакетами для оптимизации производительности
            UserDevice::query()->chunkById($batchSize, function ($devices) use ($bar) {
                foreach ($devices as $device) {
                    // Получаем случайные данные для устройства
                    $deviceData = DeviceDataFactory::getRandomDeviceData();
                    
                    // Обновляем информацию об устройстве
                    $device->name = $deviceData['name'];
                    $device->brand = $deviceData['brand'];
                    $device->device_model = $deviceData['device_model'];
                    $device->android_id = $deviceData['android_id'];
                    $device->android_version = $deviceData['android_version'];
                    $device->manufacturer = $deviceData['manufacturer'];
                    $device->save();
                    
                    $bar->advance();
                }
            });
            
            DB::commit();
            $bar->finish();
            
            $this->newLine();
            $this->info('Все данные устройств пользователей успешно обновлены!');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            
            $this->newLine();
            $this->error('Произошла ошибка при обновлении данных: ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
} 