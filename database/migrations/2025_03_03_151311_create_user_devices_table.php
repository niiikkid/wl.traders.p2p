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
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name')->comment('Название устройства');
            $table->string('token')->unique()->comment('Токен для доступа к API');
            $table->string('android_id')->nullable()->unique()->comment('Android ID устройства');
            $table->string('device_model')->nullable()->comment('Модель устройства');
            $table->string('android_version')->nullable()->comment('Версия Android');
            $table->string('manufacturer')->nullable()->comment('Производитель устройства');
            $table->string('brand')->nullable()->comment('Бренд устройства');
            $table->timestamp('connected_at')->nullable()->comment('Дата подключения устройства');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
