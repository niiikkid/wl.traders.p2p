<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('market:prices:refresh')->everyThreeMinutes();
Schedule::command('funds:on-hold:execute')->everyMinute();
Schedule::command('users:device-pings:prune')->everyMinute();
Schedule::command('users:online-periods:prune')->daily();
Schedule::command('users:online-pings:prune')->hourly();
Schedule::command('users:activity-logs:prune')->daily();
Schedule::command('payment-details:limits:reset-daily')->daily();
Schedule::command('payment-details:limits:reset-monthly')->hourly();
Schedule::command('market:filters:refresh')->hourly();
Schedule::command('telescope:prune --hours=48')->daily();
Schedule::command('maintenance:sms-logs:prune-orphans')->daily();
Schedule::command('users:online:disconnect-inactive')->everyMinute();
Schedule::command('payouts:available:notify-traders')->everyMinute()->withoutOverlapping();

// Обновление статистики API логов мерчанта каждые 5 минут (включая вчерашний день)
Schedule::command('merchant-api:stats:update')->everyFiveMinutes();
Schedule::command('dashboard:stats:cache')->everyFifteenMinutes();
