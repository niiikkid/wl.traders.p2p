<?php

namespace App\Console\Commands;

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
    protected $signature = 'app:install';

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
        $tableColumn = 'Tables_in_' . $databaseName;

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        foreach ($tables as $table) {
            $tableName = $table->$tableColumn;
            DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        $this->info('All tables dropped successfully.');

        // Восстановление базы данных из сырого SQL файла basedb.sql в корне проекта
        $dump_path = base_path('basedb.sql');
        if (file_exists($dump_path)) {
            DB::unprepared(file_get_contents($dump_path));
            $this->info('Database restored from basedb.sql');
        } else {
            $this->warn('basedb.sql not found at project root. Skipping DB restore.');
        }

        Artisan::call('migrate --force');

        //services()->settings()->createAll();

        //commands
        Artisan::call('app:load-filter-conditions');
        Artisan::call('app:update-p2p-prices');
    }
}
