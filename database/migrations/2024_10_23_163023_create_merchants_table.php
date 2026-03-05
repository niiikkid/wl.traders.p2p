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
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('name')->nullable();
            $table->longText('description')->nullable();
            $table->string('domain')->nullable();
            $table->longText('callback_url')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->boolean('active')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
