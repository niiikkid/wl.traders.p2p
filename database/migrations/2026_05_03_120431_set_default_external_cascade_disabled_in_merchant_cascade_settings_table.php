<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('merchant_cascade_settings')->insertUsing(
            ['merchant_id', 'cascade_enabled', 'allow_internal_providers', 'allow_external_providers', 'created_at', 'updated_at'],
            DB::table('merchants')
                ->leftJoin('merchant_cascade_settings', 'merchant_cascade_settings.merchant_id', '=', 'merchants.id')
                ->whereNull('merchant_cascade_settings.merchant_id')
                ->selectRaw('merchants.id, ?, ?, ?, ?, ?', [true, true, false, $now, $now])
        );

        Schema::table('merchant_cascade_settings', function (Blueprint $table) {
            $table->boolean('allow_external_providers')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('merchant_cascade_settings', function (Blueprint $table) {
            $table->boolean('allow_external_providers')->default(true)->change();
        });
    }
};
