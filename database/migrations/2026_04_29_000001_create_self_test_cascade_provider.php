<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Ранее миграция создавала запись провайдера self-test по умолчанию.
 * Интеграция self-test настраивается вручную в админке; автосоздание отключено.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
