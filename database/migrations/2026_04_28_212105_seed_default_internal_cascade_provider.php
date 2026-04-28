<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cascade_providers')) {
            return;
        }

        $alreadyExists = DB::table('cascade_providers')
            ->where('code', 'internal')
            ->where('provider_type', 'internal')
            ->exists();

        if ($alreadyExists) {
            return;
        }

        DB::table('cascade_providers')->insert([
            'code' => 'internal',
            'name' => 'Internal',
            'provider_type' => 'internal',
            'user_id' => null,
            'is_active' => true,
            'priority' => 0,
            'min_profit_percent' => 0,
            'base_url' => null,
            'access_token' => null,
            'callback_url' => null,
            'currency_code' => null,
            'timeout' => 10,
            'verify_ssl' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('cascade_providers')) {
            return;
        }

        DB::table('cascade_providers')
            ->where('code', 'internal')
            ->where('provider_type', 'internal')
            ->whereNull('user_id')
            ->delete();
    }
};
