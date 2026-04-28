<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cascade_deals', function (Blueprint $table) {
            $table->string('dispute_status')->nullable()->after('manual_control');
            $table->string('dispute_reason')->nullable()->after('dispute_status');
            $table->json('dispute_receipts')->nullable()->after('dispute_reason');
            $table->json('dispute_history')->nullable()->after('dispute_receipts');
            $table->timestamp('dispute_canceled_at')->nullable()->after('dispute_history');
        });

        DB::table('cascade_deals')
            ->where('sub_status', 'waiting_details_to_be_selected')
            ->update(['sub_status' => 'waiting_for_payment']);
    }

    public function down(): void
    {
        Schema::table('cascade_deals', function (Blueprint $table) {
            $table->dropColumn([
                'dispute_status',
                'dispute_reason',
                'dispute_receipts',
                'dispute_history',
                'dispute_canceled_at',
            ]);
        });
    }
};
