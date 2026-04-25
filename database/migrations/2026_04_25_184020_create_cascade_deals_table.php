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
        Schema::create('cascade_deals', function (Blueprint $table) {
            $table->id();
            
            // Идентификаторы
            $table->uuid('uuid')->unique();
            $table->string('external_id')->nullable();
            
            // Связи
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('merchant_client_id')->nullable()->constrained('merchant_clients')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            
            // Суммы и экономика (все денежные суммы хранятся как строки)
            $table->string('amount')->nullable(); // Текущая сумма сделки
            $table->string('initial_amount')->nullable(); // Изначальная сумма при создании
            $table->string('currency')->nullable(); // Валюта сделки (RUB, USD и т.д.)
            $table->string('debit')->nullable(); // Сумма, получаемая от провайдера ликвидности в USDT
            $table->string('credit')->nullable(); // Сумма, выплачиваемая мерчанту в USDT
            $table->string('service_profit')->nullable(); // Прибыль сервиса за операцию в USDT (debit - credit)
            
            // Внутренние расчеты с мерчантом
            $table->string('usdt_amount')->nullable(); // Сумма amount после конвертации по курсу в USDT
            $table->string('fee')->nullable(); // Комиссия, забираемая у мерчанта в USDT
            $table->float('fee_rate', 8, 2)->nullable(); // Комиссия в процентах, забираемая у мерчанта
            
            // Курс и рынок
            $table->string('market')->nullable(); // Рынок (bybit, binance, rapira)
            $table->string('conversion_price')->nullable(); // Курс обмена
            $table->timestamp('rate_fixed_at')->nullable(); // Дата фиксации курса
            
            // Статусы
            $table->string('status')->nullable(); // Статус сделки (pending, success, fail)
            $table->string('sub_status')->nullable(); // Подстатус сделки
            
            // Провайдер
            $table->foreignId('selected_provider_id')->nullable()->constrained('cascade_providers')->nullOnDelete(); // Провайдер-победитель
            $table->unsignedBigInteger('selected_transaction_id')->nullable(); // ID победившей транзакции (foreign key добавим позже)
            
            // Детали сделки
            $table->string('payment_method')->nullable(); // Метод оплаты (c2c, card и т.д.)
            $table->json('gateway')->nullable(); // Данные шлюза (code, name, logo_link)
            $table->json('details')->nullable(); // Детали платежа (type, initials, value)
            
            // Callback
            $table->longText('callback_url')->nullable(); // URL для callback'ов мерчанту
            
            // Даты
            $table->timestamp('finished_at')->nullable(); // Дата завершения сделки
            $table->timestamps();
            
            // Индексы
            $table->index('uuid');
            $table->index('external_id');
            $table->index(['merchant_id', 'status']);
            $table->index(['merchant_id', 'created_at']);
            $table->index('status');
            $table->index('selected_provider_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cascade_deals');
    }
};
