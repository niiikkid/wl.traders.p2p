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
        Schema::table('merchant_clients', function (Blueprint $table) {
            $table->dropColumn('traffic_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_clients', function (Blueprint $table) {
            $table->string('traffic_type')->nullable()->after('client_id');
        });
    }
};
