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
        Schema::create('funds_on_holds', function (Blueprint $table) {
            $table->id();
            $table->string('amount')->nullable();
            $table->string('currency')->nullable();
            $table->foreignId('source_wallet_id')->nullable();
            $table->string('source_wallet_balance_type')->nullable();
            $table->foreignId('destination_wallet_id')->nullable();
            $table->string('destination_wallet_balance_type')->nullable();
            $table->unsignedBigInteger('holdable_id')->nullable();
            $table->string('holdable_type')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('hold_until')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funds_on_holds');
    }
};
