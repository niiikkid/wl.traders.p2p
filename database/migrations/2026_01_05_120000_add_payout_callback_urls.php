<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->longText('payout_callback_url')->nullable()->after('callback_url');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->longText('callback_url')->nullable()->after('initials');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn('callback_url');
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('payout_callback_url');
        });
    }
};



