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
            $table->boolean('is_visible_for_all')
                ->default(true)
                ->after('cover_image_path');

            $table->json('visible_role_names')
                ->nullable()
                ->after('is_visible_for_all');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_posts', function (Blueprint $table) {
            $table->dropColumn([
                'is_visible_for_all',
                'visible_role_names',
            ]);
        });
    }
};
