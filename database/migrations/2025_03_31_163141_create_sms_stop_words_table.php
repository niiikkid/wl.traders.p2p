<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\SmsStopWord;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sms_stop_words', function (Blueprint $table) {
            $table->id();
            $table->string('word');
        });

        // Заполнение таблицы начальными данными
        $stopWords = [
            'поступил платёж',
            'отказ',
            'otkaz',
            'отклонено',
            'отклонена',
            'заблокирован',
            'заблокирована',
        ];

        foreach ($stopWords as $word) {
            SmsStopWord::create(['word' => $word]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_stop_words');
    }
};
