<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cascade_deals', 'order_id')) {
            return;
        }

        if ($this->hasAnyOrderIdIndex()) {
            return;
        }

        Schema::table('cascade_deals', function (Blueprint $table): void {
            $table->index('order_id', 'cascade_deals_order_id_index');
        });
    }

    public function down(): void
    {
        if (! $this->hasNamedIndex('cascade_deals_order_id_index')) {
            return;
        }

        Schema::table('cascade_deals', function (Blueprint $table): void {
            $table->dropIndex('cascade_deals_order_id_index');
        });
    }

    private function hasAnyOrderIdIndex(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND column_name = ? AND index_name <> ?',
            [DB::getDatabaseName(), 'cascade_deals', 'order_id', 'PRIMARY']
        );

        return ((int) ($row->aggregate ?? 0)) > 0;
    }

    private function hasNamedIndex(string $indexName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [DB::getDatabaseName(), 'cascade_deals', $indexName]
        );

        return ((int) ($row->aggregate ?? 0)) > 0;
    }
};
