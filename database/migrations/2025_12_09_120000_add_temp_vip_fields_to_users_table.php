<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('temp_vip_active_until')->nullable()->after('is_vip');
            $table->boolean('temp_vip_can_activate')->default(false)->after('temp_vip_active_until');
            $table->timestamp('temp_vip_progress_start_at')->nullable()->after('temp_vip_can_activate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'temp_vip_active_until',
                'temp_vip_can_activate',
                'temp_vip_progress_start_at',
            ]);
        });
    }
};

