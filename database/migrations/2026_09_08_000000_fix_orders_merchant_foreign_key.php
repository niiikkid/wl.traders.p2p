<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoOrphanedOrders('merchants');
        $this->assertNoDuplicateExternalIds('orders');
        $this->assertNoDuplicateExternalIds('payouts');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign('fk_orders_merchant_id');
            $table->foreign('merchant_id', 'fk_orders_merchant_id')
                ->references('id')
                ->on('merchants')
                ->nullOnDelete();
            $table->unique(
                ['merchant_id', 'external_id'],
                'orders_merchant_external_id_unique',
            );
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropIndex('payouts_merchant_id_external_id_index');
            $table->unique(
                ['merchant_id', 'external_id'],
                'payouts_merchant_external_id_unique',
            );
        });
    }

    public function down(): void
    {
        $this->assertNoOrphanedOrders('users');

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropUnique('payouts_merchant_external_id_unique');
            $table->index(['merchant_id', 'external_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_merchant_external_id_unique');
            $table->dropForeign('fk_orders_merchant_id');
            $table->foreign('merchant_id', 'fk_orders_merchant_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    private function assertNoOrphanedOrders(string $referencedTable): void
    {
        $count = DB::table('orders')
            ->whereNotNull('merchant_id')
            ->whereNotExists(function ($query) use ($referencedTable) {
                $query->selectRaw('1')
                    ->from($referencedTable)
                    ->whereColumn("{$referencedTable}.id", 'orders.merchant_id');
            })
            ->count();

        if ($count > 0) {
            throw new RuntimeException(
                "Cannot safely link orders.merchant_id to {$referencedTable}.id: {$count} order(s) require manual mapping. No order data was changed.",
            );
        }
    }

    private function assertNoDuplicateExternalIds(string $table): void
    {
        $duplicateExists = DB::table($table)
            ->select(['merchant_id', 'external_id'])
            ->whereNotNull('merchant_id')
            ->whereNotNull('external_id')
            ->groupBy(['merchant_id', 'external_id'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateExists) {
            throw new RuntimeException(
                "Cannot safely add {$table} external ID uniqueness: duplicate merchant_id/external_id pairs require manual resolution. No rows were deleted.",
            );
        }
    }
};
