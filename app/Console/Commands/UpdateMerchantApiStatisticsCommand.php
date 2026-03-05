<?php

namespace App\Console\Commands;

use App\Contracts\MerchantApiStatisticsServiceContract;
use App\Models\MerchantApiStatistic;
use Illuminate\Console\Command;

class UpdateMerchantApiStatisticsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-stats:update {--full : Update all statistics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update merchant API statistics';

    /**
     * Execute the console command.
     */
    public function handle(MerchantApiStatisticsServiceContract $statisticsService): int
    {
        $this->info('Updating merchant API statistics...');

        // Проверяем, есть ли данные в таблице статистики
        $hasData = MerchantApiStatistic::count() > 0;

        // Если данных нет или указан флаг --full, выполняем полное обновление
        if ($this->option('full') || !$hasData) {
            if (!$hasData && !$this->option('full')) {
                $this->info('No statistics data found. Performing full update...');
            }

            // Полное обновление статистики (можно запускать раз в день)
            $fromDate = now()->subYears(2); // Подразумеваем, что проект не старше 2 лет
            $toDate = now();
            $statisticsService->updateStatistics($fromDate, $toDate);
            $this->info('Full statistics update completed');
        } else {
            // Обновление за вчера и сегодня (для учета последних изменений)
            $statisticsService->updateTodayStatistics();
            $this->info('Today\'s and yesterday\'s statistics update completed');
        }

        return Command::SUCCESS;
    }
}
