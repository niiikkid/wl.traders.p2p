<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->date('monthly_limits_last_reset_on')
                ->nullable()
                ->after('monthly_limit_reset_day');
        });
    }

    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropColumn('monthly_limits_last_reset_on');
        });
    }
};
