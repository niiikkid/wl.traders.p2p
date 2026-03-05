<?php

namespace App\Console\Commands;

use App\Enums\MarketEnum;
use App\Services\Market\Utils\Parser\BinanceParser;
use App\Services\Market\Utils\Parser\ByBitParser;
use App\Services\Money\Currency;
use Illuminate\Console\Command;

class LoadFilterConditionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:load-filter-conditions';

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
        services()->market()->loadFilterConditions();
    }
}
