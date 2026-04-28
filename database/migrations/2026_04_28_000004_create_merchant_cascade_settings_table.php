<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_cascade_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->unique()->constrained('merchants')->cascadeOnDelete();
            $table->boolean('cascade_enabled')->default(true);
            $table->boolean('allow_internal_providers')->default(true);
            $table->boolean('allow_external_providers')->default(true);
            $table->json('allowed_provider_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_cascade_settings');
    }
};
