<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'cascade_provider_logs',
            'cascade_deal_events',
            'cascade_transactions',
            'cascade_deals',
            'cascade_providers',
            'cascade_merchant_logs',
            'merchant_cascade_settings',
            'merchant_api_credentials',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();

        Schema::table('payouts', function (Blueprint $table): void {
            foreach ([
                'api_version',
                'callback_payload_revision',
                'last_callback_delivered_revision',
            ] as $column) {
                if (Schema::hasColumn('payouts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible: removed API v2/Cascade data cannot be restored safely.
    }
};
