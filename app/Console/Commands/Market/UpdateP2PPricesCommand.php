<?php

namespace App\Console\Commands\Market;

use Illuminate\Console\Command;

class UpdateP2PPricesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'market:prices:refresh';

    protected $aliases = ['app:update-p2p-prices'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh cached P2P market prices.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        services()->market()->loadAllPrices();
    }
}
