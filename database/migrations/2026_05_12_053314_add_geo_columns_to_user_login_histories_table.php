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
        Schema::table('user_login_histories', function (Blueprint $table) {
            $table->string('country_code', 4)->nullable()->after('location');
            $table->string('country')->nullable()->after('country_code');
            $table->string('region')->nullable()->after('country');
            $table->string('city')->nullable()->after('region');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_login_histories', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'country', 'region', 'city']);
        });
    }
};
