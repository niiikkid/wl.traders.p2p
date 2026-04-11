<?php

use App\Enums\ManualControlProcessingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table
                ->timestamp('manual_control_confirmed_at')
                ->nullable()
                ->after('manual_control_reject_reason');
            $table
                ->timestamp('manual_control_rejected_at')
                ->nullable()
                ->after('manual_control_confirmed_at');
        });

        DB::table('orders')
            ->where('manual_control_acquiring', true)
            ->where('manual_control_processing_status', ManualControlProcessingStatus::CONFIRMED->value)
            ->whereNull('manual_control_confirmed_at')
            ->update([
                'manual_control_confirmed_at' => DB::raw('COALESCE(finished_at, updated_at)'),
            ]);

        DB::table('orders')
            ->where('manual_control_acquiring', true)
            ->where('manual_control_processing_status', ManualControlProcessingStatus::REJECTED->value)
            ->whereNull('manual_control_rejected_at')
            ->update([
                'manual_control_rejected_at' => DB::raw('COALESCE(finished_at, updated_at)'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'manual_control_rejected_at',
                'manual_control_confirmed_at',
            ]);
        });
    }
};
