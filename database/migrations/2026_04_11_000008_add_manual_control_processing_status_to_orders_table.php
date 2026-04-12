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
                ->string('manual_control_processing_status')
                ->nullable()
                ->after('manual_control_confirmation_type_set_at');
        });

        DB::table('orders')
            ->where('manual_control_acquiring', true)
            ->whereNull('manual_control_processing_status')
            ->update([
                'manual_control_processing_status' => ManualControlProcessingStatus::PENDING->value,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('manual_control_processing_status');
        });
    }
};
