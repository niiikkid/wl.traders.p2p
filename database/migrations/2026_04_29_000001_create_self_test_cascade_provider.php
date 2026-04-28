<?php

use App\Enums\ProviderType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('cascade_providers')->updateOrInsert(
            ['code' => 'self-test'],
            [
                'name' => 'Self-test Cascade Provider',
                'provider_type' => ProviderType::EXTERNAL->value,
                'is_active' => false,
                'priority' => 0,
                'min_profit_percent' => 0,
                'timeout' => 10,
                'verify_ssl' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('cascade_providers')
            ->where('code', 'self-test')
            ->delete();
    }
};
