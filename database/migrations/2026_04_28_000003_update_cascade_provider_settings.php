<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cascade_providers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('provider_type')->constrained('users')->nullOnDelete();
            $table->decimal('min_profit_percent', 8, 4)->default(0)->after('priority');
        });

        DB::table('cascade_providers')
            ->whereNull('timeout')
            ->update(['timeout' => 10]);

        DB::table('cascade_providers')
            ->where('timeout', '>', 10)
            ->update(['timeout' => 10]);

        Schema::table('cascade_providers', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }

    public function down(): void
    {
        Schema::table('cascade_providers', function (Blueprint $table) {
            $table->float('weight')->nullable()->after('is_active');
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'min_profit_percent']);
        });
    }
};
