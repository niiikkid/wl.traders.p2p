<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cascade_providers', function (Blueprint $table): void {
            if (Schema::hasColumn('cascade_providers', 'description')) {
                $table->dropColumn('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cascade_providers', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('provider_type');
        });
    }
};
