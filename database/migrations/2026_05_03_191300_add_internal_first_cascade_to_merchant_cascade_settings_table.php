<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_cascade_settings', function (Blueprint $table) {
            $table->boolean('internal_first_cascade_enabled')->default(false)->after('manual_control_external_only');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_cascade_settings', function (Blueprint $table) {
            $table->dropColumn('internal_first_cascade_enabled');
        });
    }
};
