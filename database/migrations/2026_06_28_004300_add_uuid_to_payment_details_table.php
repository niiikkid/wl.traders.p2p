<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->string('uuid')->nullable()->after('id');
        });

        DB::table('payment_details')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('payment_details')
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        Schema::table('payment_details', function (Blueprint $table) {
            $table->string('uuid')->nullable(false)->unique()->change();
            $table->index('uuid', 'idx_payment_details_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropIndex('idx_payment_details_uuid');
            $table->dropColumn('uuid');
        });
    }
};
