<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DisconnectInactiveUsersCommand extends Command
{
    private const PANEL_ACTIVITY_THRESHOLD_MINUTES = 30;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:disconnect-inactive-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Отключает трейдеров в статусе «работает», если в панели не было активности более 30 минут';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $inactiveUsers = User::role('Trader')
            ->where('is_online', true)
            ->whereHas('orders')
            ->get()
            ->filter(fn (User $user): bool => $this->hasNoRecentPanelActivity($user));

        $inactiveUsers->each(function (User $user): void {
            $user->update(['is_online' => false]);
        });

        return Command::SUCCESS;
    }

    private function hasNoRecentPanelActivity(User $user): bool
    {
        $onlineAt = cache()->get("user-online-at-{$user->id}");

        if (! is_string($onlineAt) || $onlineAt === '') {
            return true;
        }

        try {
            return Carbon::parse($onlineAt)->lt(
                now()->subMinutes(self::PANEL_ACTIVITY_THRESHOLD_MINUTES)
            );
        } catch (\Throwable) {
            return true;
        }
    }
}
