<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_cascade_settings', function (Blueprint $table) {
            $table->boolean('manual_control_external_only')->default(false)->after('allow_external_providers');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_cascade_settings', function (Blueprint $table) {
            $table->dropColumn('manual_control_external_only');
        });
    }
};
