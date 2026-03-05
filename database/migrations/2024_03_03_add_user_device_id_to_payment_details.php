<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->foreignId('user_device_id')->nullable()->after('user_id');
        });
    }

    public function down()
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropForeign(['user_device_id']);
            $table->dropColumn('user_device_id');
        });
    }
}; 