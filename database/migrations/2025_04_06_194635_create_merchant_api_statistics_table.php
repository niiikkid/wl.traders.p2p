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
        Schema::create('merchant_api_statistics', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->boolean('is_successful')->index();
            $table->string('currency', 10)->nullable()->index();
            $table->integer('count')->default(0);
            $table->decimal('sum_amount', 24, 8)->default(0);
            $table->timestamps();
            
            // Уникальный индекс для быстрого поиска и обновления
            $table->unique(['date', 'is_successful', 'currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_api_statistics');
    }
};
