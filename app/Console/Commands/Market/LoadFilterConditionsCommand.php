<?php

namespace App\Console\Commands\Market;

use Illuminate\Console\Command;

class LoadFilterConditionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'market:filters:refresh';

    protected $aliases = ['app:load-filter-conditions'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh market filter conditions.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        services()->market()->loadFilterConditions();
    }
}
