<?php

use App\Models\Order;
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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('sub_status')->nullable()->after('status');
        });

        \Illuminate\Support\Facades\DB::transaction(function () {
            Order::query()
                ->where('status', \App\Enums\OrderStatus::SUCCESS)
                ->whereDoesntHave('dispute')
                ->update(['sub_status' => \App\Enums\OrderSubStatus::SUCCESSFULLY_PAID]);

            Order::query()
                ->where('status', \App\Enums\OrderStatus::SUCCESS)
                ->whereHas('dispute')
                ->update(['sub_status' => \App\Enums\OrderSubStatus::SUCCESSFULLY_PAID_BY_RESOLVED_DISPUTE]);

            Order::query()
                ->where('status', \App\Enums\OrderStatus::PENDING)
                ->whereDoesntHave('dispute')
                ->update(['sub_status' => \App\Enums\OrderSubStatus::WAITING_FOR_PAYMENT]);

            Order::query()
                ->where('status', \App\Enums\OrderStatus::PENDING)
                ->whereHas('dispute')
                ->update(['sub_status' => \App\Enums\OrderSubStatus::WAITING_FOR_DISPUTE_TO_BE_RESOLVED]);

            Order::query()
                ->where('status', \App\Enums\OrderStatus::FAIL)
                ->whereDoesntHave('dispute')
                ->update(['sub_status' => \App\Enums\OrderSubStatus::EXPIRED]);

            Order::query()
                ->where('status', \App\Enums\OrderStatus::FAIL)
                ->whereHas('dispute')
                ->update(['sub_status' => \App\Enums\OrderSubStatus::CANCELED_BY_DISPUTE]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('sub_status');
        });
    }
};
