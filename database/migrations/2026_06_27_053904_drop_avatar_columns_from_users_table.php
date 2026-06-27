<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_uuid', 'avatar_style']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_uuid')->nullable()->after('email_verified_at');
            $table->string('avatar_style')->nullable()->after('avatar_uuid');
        });
    }
};
