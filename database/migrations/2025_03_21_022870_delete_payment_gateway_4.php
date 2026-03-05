<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('payment_gateways')->where('id', 4)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // В случае отката миграции, мы можем восстановить удаленный Payment Gateway
        // Предполагается, что у нас есть данные о Payment Gateway с ID 4
        DB::table('payment_gateways')->insert([
            'id' => 4,
            'name' => 'Deleted Payment Gateway',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
