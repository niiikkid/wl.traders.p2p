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
            $table->foreignId('team_leader_id')->nullable()->after('trader_id');
            $table->string('team_leader_profit')->default(0)->after('trader_profit');
            $table->float('team_leader_commission_rate', 2)->default(0)->after('trader_commission_rate');
        });
        
        \Illuminate\Support\Facades\DB::table('orders')
            ->update([
                'team_leader_profit' => 0,
                'team_leader_commission_rate' => 0,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('team_leader_id');
            $table->dropColumn('team_leader_profit');
            $table->dropColumn('team_leader_commission_rate');
        });
    }
};
