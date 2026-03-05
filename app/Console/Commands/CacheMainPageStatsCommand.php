<?php

namespace App\Console\Commands;

use App\Jobs\CacheMainPageStatsJob;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class CacheMainPageStatsCommand extends Command
{
    protected $signature = 'app:cache-main-page-stats';

    protected $description = 'Перекэшировать данные главных страниц для всех пользователей.';

    public function handle(): int
    {
        $queueSize = Queue::connection('redis')->size(CacheMainPageStatsJob::QUEUE);

        if ($queueSize > 0) {
            $this->info("Очередь ".CacheMainPageStatsJob::QUEUE." занята ({$queueSize} задач), запуск пропущен.");

            return self::SUCCESS;
        }

        $users = User::query()
            ->role(['Merchant', 'Trader', 'Team Leader', 'Super Admin'])
            ->with('roles')
            ->get()
            ->unique('id');

        $dispatched = 0;

        foreach ($users as $user) {
            $viewModes = $this->resolveViewModesForUser($user);

            foreach ($viewModes as $viewMode) {
                CacheMainPageStatsJob::dispatch($user->id, $viewMode);
                $dispatched++;
            }
        }

        $this->info("Отправлено задач: {$dispatched}");

        return self::SUCCESS;
    }

    private function resolveViewModesForUser(User $user): array
    {
        if ($user->hasRole('Super Admin')) {
            return ['admin', 'trader', 'merchant', 'leader'];
        }

        $viewModes = [];

        if ($user->hasRole('Merchant')) {
            $viewModes[] = 'merchant';
        }

        if ($user->hasRole('Trader')) {
            $viewModes[] = 'trader';
        }

        if ($user->hasRole('Team Leader')) {
            $viewModes[] = 'leader';
        }

        return array_values(array_unique($viewModes));
    }
}

