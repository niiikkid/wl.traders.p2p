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
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->string('currency')->nullable();
            $table->string('min_limit')->nullable();
            $table->string('max_limit')->nullable();
            $table->longText('sms_senders')->nullable();
            $table->float('commission_rate', 2)->nullable();
            $table->float('service_commission_rate', 2)->default(9)->nullable();
            $table->boolean('is_active')->default(true);
            $table->longText('detail_types')->nullable();
            $table->longText('sub_payment_gateways')->nullable();
            $table->unsignedInteger('reservation_time')->default(10);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
