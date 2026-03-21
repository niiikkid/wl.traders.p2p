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
        Schema::create('enabled_card_min_amount_levels', function (Blueprint $table) {
            $table->id();
            $table->string('currency', 10);
            $table->unsignedBigInteger('min_amount');
            $table->timestamps();

            $table->unique(['currency', 'min_amount']);
            $table->index('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enabled_card_min_amount_levels');
    }
};
