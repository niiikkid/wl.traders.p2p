<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InstallSettingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:install-settings';

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
        services()->settings()->createAll();

        $this->comment('Новые настройки были добавлены.');
    }
}
