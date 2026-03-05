<?php

namespace App\Console\Commands;

use App\Services\Money\Currency;
use Illuminate\Console\Command;

class UpdateP2PPricesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-p2p-prices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        services()->market()->loadAllPrices();
    }
}
