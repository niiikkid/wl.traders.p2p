<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->string('uuid')->nullable()->unique()->after('id');
        });

        DB::table('disputes')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(100, function ($disputes): void {
                foreach ($disputes as $dispute) {
                    DB::table('disputes')
                        ->where('id', $dispute->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        Schema::table('disputes', function (Blueprint $table) {
            $table->string('uuid')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
