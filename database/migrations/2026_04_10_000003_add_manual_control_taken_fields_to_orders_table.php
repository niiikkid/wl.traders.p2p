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
            $table->foreignId('manual_control_taken_by_user_id')
                ->nullable()
                ->after('manual_control_cardholder_name')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('manual_control_taken_at')
                ->nullable()
                ->after('manual_control_taken_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manual_control_taken_by_user_id');
            $table->dropColumn('manual_control_taken_at');
        });
    }
};
