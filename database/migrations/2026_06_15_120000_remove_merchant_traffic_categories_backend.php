<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'merchant_traffic_categories_enabled')
            ->delete();

        Schema::dropIfExists('category_user');
        Schema::dropIfExists('category_merchant');
        Schema::dropIfExists('categories');

        Schema::table('user_metas', function (Blueprint $table) {
            $table->dropColumn('allowed_categories');
        });
    }

    public function down(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->boolean('enabled_by_default')->default(false);
            $table->timestamps();
        });

        Schema::create('category_merchant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'merchant_id']);
        });

        Schema::create('category_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['category_id', 'user_id']);
            $table->index(['user_id', 'enabled']);
        });

        Schema::table('user_metas', function (Blueprint $table) {
            $table->json('allowed_categories')->nullable()->after('allowed_markets');
        });

        DB::table('settings')->insertOrIgnore([
            'key' => 'merchant_traffic_categories_enabled',
            'value' => 0,
        ]);
    }
};
