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
        Schema::table('cascade_deals', function (Blueprint $table) {
            $table->string('external_id')->nullable(false)->change();
        });

        Schema::table('cascade_deals', function (Blueprint $table) {
            $table->unique(
                ['merchant_id', 'external_id'],
                'cascade_deals_merchant_id_external_id_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cascade_deals', function (Blueprint $table) {
            $table->dropUnique('cascade_deals_merchant_id_external_id_unique');
        });

        Schema::table('cascade_deals', function (Blueprint $table) {
            $table->string('external_id')->nullable()->change();
        });
    }
};
