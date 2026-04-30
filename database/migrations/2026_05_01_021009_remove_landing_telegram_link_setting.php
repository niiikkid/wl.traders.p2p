<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'landing_telegram_link')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'landing_telegram_link',
            'value' => null,
        ]);
    }
};
