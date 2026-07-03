<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Банки (платёжные шлюзы) намеренно не сидируются при установке —
        // они генерируются как выдуманные командой dev:test-data:generate.
        $this->call([
            SenderStopListSeeder::class,
            UserSeeder::class,
        ]);
    }
}
