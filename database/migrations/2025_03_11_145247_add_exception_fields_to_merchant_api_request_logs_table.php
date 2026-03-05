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
        Schema::table('merchant_api_request_logs', function (Blueprint $table) {
            $table->string('exception_class')->nullable()->after('error_message');
            $table->text('exception_message')->nullable()->after('exception_class');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_api_request_logs', function (Blueprint $table) {
            $table->dropColumn(['exception_class', 'exception_message']);
        });
    }
};
