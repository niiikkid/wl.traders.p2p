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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('id');
            $table->string('network')->nullable()->after('address');
            $table->string('tx_hash')->nullable()->after('network');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('external_id');
            $table->dropColumn('network');
            $table->dropColumn('tx_hash');
        });
    }
};
