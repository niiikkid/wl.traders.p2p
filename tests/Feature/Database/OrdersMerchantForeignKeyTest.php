<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrdersMerchantForeignKeyTest extends TestCase
{
    public function test_orders_merchant_id_references_merchants_table(): void
    {
        $foreignKey = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'orders')
            ->where('CONSTRAINT_NAME', 'fk_orders_merchant_id')
            ->value('REFERENCED_TABLE_NAME');

        $this->assertSame('merchants', $foreignKey);
    }

    public function test_order_external_id_is_unique_per_merchant(): void
    {
        $this->assertUniqueIndexColumns(
            'orders',
            'orders_merchant_external_id_unique',
            ['merchant_id', 'external_id'],
        );
    }

    public function test_payout_external_id_is_unique_per_merchant(): void
    {
        $this->assertUniqueIndexColumns(
            'payouts',
            'payouts_merchant_external_id_unique',
            ['merchant_id', 'external_id'],
        );
    }

    private function assertUniqueIndexColumns(string $table, string $index, array $expectedColumns): void
    {
        $columns = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->where('NON_UNIQUE', 0)
            ->orderBy('SEQ_IN_INDEX')
            ->pluck('COLUMN_NAME')
            ->all();

        $this->assertSame($expectedColumns, $columns);
    }
}
