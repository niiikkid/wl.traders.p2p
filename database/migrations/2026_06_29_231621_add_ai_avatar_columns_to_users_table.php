<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('email_verified_at');
            $table->text('avatar_description')->nullable()->after('avatar_path');
            $table->timestamp('avatar_generated_at')->nullable()->after('avatar_description');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'avatar_description', 'avatar_generated_at']);
        });
    }
};
