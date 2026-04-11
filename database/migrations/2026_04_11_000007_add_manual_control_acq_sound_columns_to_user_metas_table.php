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
            $table->boolean('manual_control_acq_new_offer_sound_enabled')
                ->default(true)
                ->after('news_last_read_at');
            $table->string('manual_control_acq_new_offer_sound_track')
                ->nullable()
                ->after('manual_control_acq_new_offer_sound_enabled');
            $table->boolean('manual_control_acq_confirm_code_sound_enabled')
                ->default(true)
                ->after('manual_control_acq_new_offer_sound_track');
            $table->string('manual_control_acq_confirm_code_sound_track')
                ->nullable()
                ->after('manual_control_acq_confirm_code_sound_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_metas', function (Blueprint $table) {
            $table->dropColumn([
                'manual_control_acq_new_offer_sound_enabled',
                'manual_control_acq_new_offer_sound_track',
                'manual_control_acq_confirm_code_sound_enabled',
                'manual_control_acq_confirm_code_sound_track',
            ]);
        });
    }
};
