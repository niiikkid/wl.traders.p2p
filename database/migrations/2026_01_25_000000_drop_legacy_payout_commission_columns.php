<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'service_commission_rate',
            'teamlead_split_from_service',
            'teamlead_split_from_service_currency',
            'teamlead_split_from_trader',
            'teamlead_split_from_trader_currency',
        ];

        $existing = array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn('payouts', $column)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('payouts', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            if (! Schema::hasColumn('payouts', 'service_commission_rate')) {
                $table->float('service_commission_rate', 8, 2)->nullable()->after('teamlead_commission_rate');
            }
            if (! Schema::hasColumn('payouts', 'teamlead_split_from_service')) {
                $table->string('teamlead_split_from_service')->nullable()->after('teamlead_fee_currency');
            }
            if (! Schema::hasColumn('payouts', 'teamlead_split_from_service_currency')) {
                $table->string('teamlead_split_from_service_currency')->default('usdt')->after('teamlead_split_from_service');
            }
            if (! Schema::hasColumn('payouts', 'teamlead_split_from_trader')) {
                $table->string('teamlead_split_from_trader')->nullable()->after('teamlead_split_from_service_currency');
            }
            if (! Schema::hasColumn('payouts', 'teamlead_split_from_trader_currency')) {
                $table->string('teamlead_split_from_trader_currency')->default('usdt')->after('teamlead_split_from_trader');
            }
        });
    }
};
