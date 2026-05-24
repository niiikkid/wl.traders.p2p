<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_detail_schedule_id')->nullable()->after('user_id');
            $table->foreign('payment_detail_schedule_id', 'pd_schedule_fk')
                ->references('id')
                ->on('payment_detail_schedules')
                ->nullOnDelete();

            $table->index('payment_detail_schedule_id', 'pd_schedule_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropForeign(['payment_detail_schedule_id']);
            $table->dropIndex('pd_schedule_id_idx');
            $table->dropColumn('payment_detail_schedule_id');
        });
    }
};
