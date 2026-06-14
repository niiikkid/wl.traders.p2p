<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')->whereNotNull('agent_id')->update(['agent_id' => null]);

        $agentRole = Role::query()->where('name', 'Agent')->first();

        if ($agentRole === null) {
            return;
        }

        DB::table('model_has_roles')->where('role_id', $agentRole->id)->delete();
        DB::table('role_has_permissions')->where('role_id', $agentRole->id)->delete();
        $agentRole->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::findOrCreate('Agent');
    }
};
