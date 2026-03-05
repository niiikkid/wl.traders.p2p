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
        Schema::create('payout_offers', function (Blueprint $table) {
            $table->id();

            $table->string('max_amount')->nullable();
            $table->string('min_amount')->nullable();
            $table->string('currency')->nullable();

            $table->longText('detail_types')->nullable();

            $table->boolean('active')->nullable();

            $table->foreignId('payment_gateway_id')->nullable();
            $table->foreignId('owner_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_offers');
    }
};
