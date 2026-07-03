<?php

namespace App\Console\Commands\System;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class InstallAppCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:install';

    protected $aliases = ['app:install'];

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
        // Удаление всех таблиц из базы данных
        $this->info('Dropping all tables...');
        $tables = DB::select('SHOW TABLES');
        $databaseName = DB::getDatabaseName();
        $tableColumn = 'Tables_in_'.$databaseName;

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        foreach ($tables as $table) {
            $tableName = $table->$tableColumn;
            DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        $this->info('All tables dropped successfully.');

        // Полная сборка схемы из миграций (без сырого SQL-дампа)
        $this->info('Running migrations...');
        Artisan::call('migrate', ['--force' => true], $this->output);

        // Настройки приложения
        $this->info('Installing settings...');
        Artisan::call('app:install-settings', [], $this->output);

        // Справочные данные (платёжные шлюзы, стоп-листы, пользователи)
        $this->info('Seeding reference data...');
        Artisan::call('db:seed', ['--force' => true], $this->output);

        // commands
        Artisan::call('market:filters:refresh');
        Artisan::call('market:prices:refresh');

        $this->info('Installation completed.');
    }
}
