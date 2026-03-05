<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('uuid');
            $table->index(['merchant_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropIndex(['merchant_id', 'external_id']);
            $table->dropColumn('external_id');
        });
    }
};
