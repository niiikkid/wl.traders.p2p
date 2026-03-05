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
        Schema::create('payout_gateways', function (Blueprint $table) {
            $table->id();

            $table->string('uuid')->nullable();
            $table->string('name')->nullable();
            $table->string('domain')->nullable();
            $table->string('callback_url')->nullable();
            $table->boolean('enabled')->nullable();
            $table->foreignId('owner_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_gateways');
    }
};
