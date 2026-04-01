<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->string('monthly_limit')->nullable()->after('current_daily_limit');
            $table->string('current_monthly_limit')->default(0)->after('monthly_limit');
            $table->unsignedTinyInteger('monthly_limit_reset_day')->nullable()->after('current_monthly_limit');
        });
    }

    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropColumn([
                'monthly_limit',
                'current_monthly_limit',
                'monthly_limit_reset_day',
            ]);
        });
    }
};
