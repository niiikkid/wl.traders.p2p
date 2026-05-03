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
        Schema::table('cascade_providers', function (Blueprint $table): void {
            $table->unique('code', 'cascade_providers_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cascade_providers', function (Blueprint $table): void {
            $table->dropUnique('cascade_providers_code_unique');
        });
    }
};
