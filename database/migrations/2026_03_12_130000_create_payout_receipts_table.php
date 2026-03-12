<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_id')->constrained('payouts')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index('payout_id');
            $table->unique(['payout_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_receipts');
    }
};

