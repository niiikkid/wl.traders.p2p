<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('cascade_providers')->update(['priority' => 0]);

        Schema::table('cascade_providers', function (Blueprint $table) {
            $table->unsignedInteger('priority')->default(0)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cascade_providers', function (Blueprint $table) {
            $table->unsignedInteger('priority')->nullable()->change();
        });
    }
};
