<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cascade_providers', 'config')) {
            return;
        }

        Schema::table('cascade_providers', function (Blueprint $table): void {
            $table->dropColumn('config');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('cascade_providers', 'config')) {
            return;
        }

        Schema::table('cascade_providers', function (Blueprint $table): void {
            $table->json('config')->nullable()->after('min_profit_percent');
        });
    }
};
