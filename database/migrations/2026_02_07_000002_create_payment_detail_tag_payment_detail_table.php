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
        Schema::create('payment_detail_tag_payment_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_detail_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_detail_tag_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['payment_detail_id', 'payment_detail_tag_id'], 'pdt_pd_tag_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_detail_tag_payment_detail');
    }
};
