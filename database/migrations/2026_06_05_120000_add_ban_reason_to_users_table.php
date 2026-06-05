<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ban_reason', 500)->nullable()->after('banned_at');
            $table->foreignId('banned_by_user_id')
                ->nullable()
                ->after('ban_reason')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('banned_by_user_id');
            $table->dropColumn('ban_reason');
        });
    }
};
