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
        Schema::create('cascade_provider_logs', function (Blueprint $table) {
            $table->id();
            
            // Связи
            $table->foreignId('cascade_deal_id')->nullable()->constrained('cascade_deals')->cascadeOnDelete();
            $table->foreignId('cascade_transaction_id')->nullable()->constrained('cascade_transactions')->nullOnDelete();
            
            // Провайдер
            $table->foreignId('provider_id')->constrained('cascade_providers')->cascadeOnDelete(); // Провайдер
            
            // Операция
            $table->string('operation'); // Тип операции (createDeal, cancelDeal, getDeal, openDispute, getDispute)
            
            // HTTP детали
            $table->string('method'); // HTTP метод (GET, POST, PUT, DELETE)
            $table->string('url'); // URL/endpoint запроса к провайдеру
            
            // Payload
            $table->json('request_payload')->nullable(); // Тело запроса (JSON)
            $table->json('response_payload')->nullable(); // Тело ответа (JSON)
            
            // HTTP статус
            $table->unsignedInteger('status_code')->nullable(); // HTTP статус код ответа
            
            // Метрики
            $table->float('execution_time')->nullable(); // Время выполнения запроса в секундах
            $table->boolean('is_successful')->default(false); // Успешен ли запрос
            
            // Ошибки
            $table->string('error_code')->nullable(); // Код ошибки (если запрос неуспешен)
            $table->text('error_message')->nullable(); // Сообщение об ошибке (если запрос неуспешен)
            
            $table->timestamps();
            
            // Индексы
            $table->index('cascade_deal_id');
            $table->index('cascade_transaction_id');
            $table->index(['cascade_deal_id', 'provider_id']);
            $table->index('provider_id');
            $table->index('operation');
            $table->index('is_successful');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cascade_provider_logs');
    }
};
