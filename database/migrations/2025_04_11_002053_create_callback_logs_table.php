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
        Schema::create('callback_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // тип колбека: order
            $table->morphs('callbackable'); // полиморфная связь для Order
            $table->string('url'); // URL куда был отправлен колбек
            $table->json('request_data')->nullable(); // данные, которые были отправлены
            $table->json('response_data')->nullable(); // данные, полученные в ответ
            $table->integer('status_code')->nullable(); // HTTP статус код ответа
            $table->boolean('is_success')->default(false); // успешно ли выполнен запрос
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('callback_logs');
    }
};
