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
        Schema::table('user_metas', function (Blueprint $table) {
            $table->boolean('notification_sound_enabled')
                ->default(true)
                ->after('allowed_categories');
            $table->string('notification_sound_track')
                ->nullable()
                ->after('notification_sound_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_metas', function (Blueprint $table) {
            $table->dropColumn([
                'notification_sound_enabled',
                'notification_sound_track',
            ]);
        });
    }
};
