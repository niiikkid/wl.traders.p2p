<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trader_economy_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->timestamps();

            $table->unique(['user_id', 'year', 'month']);
            $table->index(['user_id', 'year', 'month']);
        });

        Schema::create('trader_economy_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trader_economy_month_id')
                ->constrained('trader_economy_months')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('day');
            $table->decimal('rate', 20, 8)->nullable();
            $table->decimal('start_balance', 20, 8)->nullable();
            $table->decimal('card_uah', 20, 8)->nullable();
            $table->decimal('end_balance', 20, 8)->nullable();
            $table->decimal('exchange_balance', 20, 8)->nullable();
            $table->decimal('circles', 20, 8)->nullable();
            $table->decimal('arbitrage_usd', 20, 8)->nullable();
            $table->decimal('expense_uah', 20, 8)->nullable();
            $table->timestamps();

            $table->unique(['trader_economy_month_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trader_economy_days');
        Schema::dropIfExists('trader_economy_months');
    }
};
