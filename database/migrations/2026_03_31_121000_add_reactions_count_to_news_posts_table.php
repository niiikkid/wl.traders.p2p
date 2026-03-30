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
        Schema::table('news_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('likes_count')
                ->default(0)
                ->after('views_count');

            $table->unsignedBigInteger('dislikes_count')
                ->default(0)
                ->after('likes_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_posts', function (Blueprint $table) {
            $table->dropColumn([
                'likes_count',
                'dislikes_count',
            ]);
        });
    }
};
