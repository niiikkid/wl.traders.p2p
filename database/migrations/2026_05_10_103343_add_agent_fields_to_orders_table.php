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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('agent_id')
                ->nullable()
                ->after('team_leader_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('agent_profit')->default(0)->after('team_leader_profit');
            $table->float('agent_commission_rate', 2)->default(0)->after('team_leader_commission_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
            $table->dropColumn(['agent_profit', 'agent_commission_rate']);
        });
    }
};
