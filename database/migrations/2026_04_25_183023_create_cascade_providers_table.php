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
        Schema::create('cascade_providers', function (Blueprint $table) {
            $table->id();
            
            // Основная информация
            $table->string('code')->unique(); // Уникальный код провайдера (например, 'internal', 'external_provider_1')
            $table->string('name'); // Название провайдера для отображения
            $table->string('provider_type'); // Тип провайдера (internal/external)
            $table->text('description')->nullable(); // Описание провайдера
            
            // Управление
            $table->boolean('is_active')->default(true); // Включен ли провайдер (попадает ли в обработчик)
            
            // Распределение трафика
            $table->float('weight')->nullable(); // Вес для распределения трафика (процент от 0 до 100)
            $table->unsignedInteger('priority')->nullable(); // Порядок приоритета (чем меньше число, тем выше приоритет)
            
            // Конфигурация
            $table->json('config')->nullable(); // Конфигурация провайдера (API ключи, URL, endpoints и т.д.)
            
            $table->timestamps();
            
            // Индексы
            $table->index('code');
            $table->index('provider_type');
            $table->index('is_active');
            $table->index('priority');
            $table->index(['is_active', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cascade_providers');
    }
};
