<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cascade_providers', function (Blueprint $table) {
            $table->foreignId('target_merchant_id')
                ->nullable()
                ->after('merchant_id')
                ->constrained('merchants')
                ->nullOnDelete();

            $table->dropUnique('cascade_providers_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cascade_providers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('target_merchant_id');
            $table->unique('code');
        });
    }
};
