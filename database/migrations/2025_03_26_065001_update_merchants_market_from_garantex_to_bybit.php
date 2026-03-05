<?php

use App\Enums\MarketEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Обновляем значения garantex на bybit в таблице merchants
        DB::table('merchants')
            ->where('market', 'garantex')
            ->update(['market' => MarketEnum::BYBIT->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
