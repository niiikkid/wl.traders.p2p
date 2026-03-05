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
            $table->string('avatar_uuid')->nullable()->after('email_verified_at');
            $table->string('avatar_style')->nullable()->after('avatar_uuid');
        });

        \App\Models\User::query()->chunk(100, function ($users) {
            foreach ($users as $user) {
                $user->update([
                    'avatar_uuid' => $user->email,
                    'avatar_style' => 'adventurer'
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_uuid');
            $table->dropColumn('avatar_style');
        });
    }
};
