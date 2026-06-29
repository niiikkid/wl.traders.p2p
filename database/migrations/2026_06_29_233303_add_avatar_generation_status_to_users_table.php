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
            $table->string('avatar_generation_status')->nullable()->after('avatar_generated_at');
            $table->timestamp('avatar_generation_requested_at')->nullable()->after('avatar_generation_status');
            $table->timestamp('avatar_generation_failed_at')->nullable()->after('avatar_generation_requested_at');
            $table->string('avatar_generation_error')->nullable()->after('avatar_generation_failed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_generation_status',
                'avatar_generation_requested_at',
                'avatar_generation_failed_at',
                'avatar_generation_error',
            ]);
        });
    }
};
