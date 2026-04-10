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
        Schema::table('orders', function (Blueprint $table) {
            $table
                ->string('manual_control_confirmation_type')
                ->nullable()
                ->after('manual_control_taken_at');
            $table
                ->timestamp('manual_control_confirmation_type_set_at')
                ->nullable()
                ->after('manual_control_confirmation_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'manual_control_confirmation_type_set_at',
                'manual_control_confirmation_type',
            ]);
        });
    }
};
