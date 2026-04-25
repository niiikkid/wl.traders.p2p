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
        Schema::create('cascade_transactions', function (Blueprint $table) {
            $table->id();
            
            // Связь с каскадной сделкой
            $table->foreignId('cascade_deal_id')->constrained('cascade_deals')->cascadeOnDelete();
            
            // Провайдер
            $table->foreignId('provider_id')->constrained('cascade_providers')->cascadeOnDelete(); // Провайдер
            
            // Статус
            $table->string('status'); // Статус транзакции (opened/failed_to_open/cancelled/accepted)
            
            // Данные провайдера
            $table->string('provider_deal_id')->nullable(); // ID сделки у провайдера (если создана)
            
            // Аудит
            $table->json('request_payload')->nullable(); // Данные запроса к провайдеру
            $table->json('response_payload')->nullable(); // Данные ответа от провайдера
            
            // Ошибки
            $table->string('error_code')->nullable(); // Код ошибки (если транзакция неуспешна)
            $table->text('error_message')->nullable(); // Сообщение об ошибке (если транзакция неуспешна)
            
            $table->timestamps();
            
            // Индексы
            $table->index('cascade_deal_id');
            $table->index(['cascade_deal_id', 'status']);
            $table->index('provider_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cascade_transactions');
    }
};
