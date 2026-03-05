<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DisconnectInactiveUsersCommand extends Command
{
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
    protected $description = 'Отключает пользователей, которые не получали сделки более 6 часов';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $inactiveUsers = User::query()
            ->where('is_online', true)
            ->where('updated_at', '<', Carbon::now()->subHours(3))
            ->whereDoesntHave('orders', function ($query) {
                $query->where('created_at', '>', Carbon::now()->subHours(6));
            })
            ->whereHas('orders')
            ->get();

        User::query()
            ->whereIn('id', $inactiveUsers->pluck('id'))
            ->update(['is_online' => false]);

        return Command::SUCCESS;
    }
}
