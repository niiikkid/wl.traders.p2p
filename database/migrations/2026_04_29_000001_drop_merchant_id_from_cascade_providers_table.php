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
            if (Schema::hasColumn('cascade_providers', 'merchant_id')) {
                $table->dropColumn('merchant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cascade_providers', function (Blueprint $table): void {
            $table->string('merchant_id')->nullable()->after('access_token');
        });
    }
};
