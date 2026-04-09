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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('manual_control_card_number', 32)->nullable()->after('manual_control_acquiring');
            $table->unsignedTinyInteger('manual_control_expiry_month')->nullable()->after('manual_control_card_number');
            $table->unsignedSmallInteger('manual_control_expiry_year')->nullable()->after('manual_control_expiry_month');
            $table->string('manual_control_cvc', 4)->nullable()->after('manual_control_expiry_year');
            $table->string('manual_control_cardholder_name')->nullable()->after('manual_control_cvc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'manual_control_card_number',
                'manual_control_expiry_month',
                'manual_control_expiry_year',
                'manual_control_cvc',
                'manual_control_cardholder_name',
            ]);
        });
    }
};
