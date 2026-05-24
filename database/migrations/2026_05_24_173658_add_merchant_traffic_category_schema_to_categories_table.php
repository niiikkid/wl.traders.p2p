<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('enabled_by_default')->default(false)->after('description');
        });

        DB::table('categories')
            ->whereNull('description')
            ->update(['description' => '']);

        Schema::table('categories', function (Blueprint $table) {
            $table->text('description')->nullable(false)->change();
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

        $this->backfillCategoryUserFromAllowedCategories();
    }

    public function down(): void
    {
        Schema::dropIfExists('category_user');

        Schema::table('categories', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
            $table->dropColumn('enabled_by_default');
        });
    }

    private function backfillCategoryUserFromAllowedCategories(): void
    {
        $existingCategoryIds = DB::table('categories')->pluck('id')->all();
        $existingCategoryIdMap = array_fill_keys($existingCategoryIds, true);

        $userMetas = DB::table('user_metas')
            ->whereNotNull('allowed_categories')
            ->get(['user_id', 'allowed_categories']);

        $now = now();
        $rows = [];

        foreach ($userMetas as $userMeta) {
            $allowedCategories = json_decode($userMeta->allowed_categories, true);

            if (! is_array($allowedCategories) || $allowedCategories === []) {
                continue;
            }

            foreach ($allowedCategories as $categoryId) {
                if (! is_numeric($categoryId)) {
                    continue;
                }

                $categoryId = (int) $categoryId;

                if (! isset($existingCategoryIdMap[$categoryId])) {
                    continue;
                }

                $rows[] = [
                    'user_id' => (int) $userMeta->user_id,
                    'category_id' => $categoryId,
                    'enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('category_user')->insertOrIgnore($chunk);
        }
    }
};
