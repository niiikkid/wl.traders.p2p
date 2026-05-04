<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_cascade_settings', function (Blueprint $table) {
            $table->renameColumn('manual_control_external_only', 'manual_control_internal_only');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_cascade_settings', function (Blueprint $table) {
            $table->renameColumn('manual_control_internal_only', 'manual_control_external_only');
        });
    }
};
