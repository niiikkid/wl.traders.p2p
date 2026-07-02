<?php

namespace App\Console\Commands\System;

use Illuminate\Console\Command;

class InstallSettingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:settings:install';

    protected $aliases = ['app:install-settings'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install missing application settings.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        services()->settings()->createAll();

        $this->comment('Новые настройки были добавлены.');
    }
}
