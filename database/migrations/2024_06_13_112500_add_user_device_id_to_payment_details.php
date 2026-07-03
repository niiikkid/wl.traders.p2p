<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payment_details', 'user_device_id')) {
            return;
        }

        Schema::table('payment_details', function (Blueprint $table) {
            $table->foreignId('user_device_id')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropColumn('user_device_id');
        });
    }
};
