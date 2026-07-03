<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->foreignId('merchant_id')
                ->nullable()
                ->after('user_id')
                ->constrained('merchants')
                ->nullOnDelete();

            $table->unique('merchant_id', 'wallets_merchant_id_unique');
        });

        $now = now();

        DB::table('merchants')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->chunkById(500, function ($merchants) use ($now): void {
                $existingMerchantWalletIds = DB::table('wallets')
                    ->whereIn('merchant_id', $merchants->pluck('id'))
                    ->pluck('merchant_id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                $existingMerchantWalletIds = array_flip($existingMerchantWalletIds);

                $rows = $merchants
                    ->reject(fn ($merchant): bool => isset($existingMerchantWalletIds[(int) $merchant->id]))
                    ->map(fn ($merchant): array => [
                        'merchant_balance' => '0',
                        'trust_balance' => '0',
                        'provider_balance' => '0',
                        'reserve_balance' => '0',
                        'commission_balance' => '0',
                        'teamleader_balance' => '0',
                        'agent_balance' => '0',
                        'user_id' => $merchant->user_id,
                        'merchant_id' => $merchant->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->values()
                    ->all();

                if ($rows !== []) {
                    DB::table('wallets')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique('wallets_merchant_id_unique');
            $table->dropConstrainedForeignId('merchant_id');
        });
    }
};
