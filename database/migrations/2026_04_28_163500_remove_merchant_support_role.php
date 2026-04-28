<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::query()
            ->where('name', 'Merchant Support')
            ->where('guard_name', 'web')
            ->first();

        if (! $role) {
            return;
        }

        DB::table('model_has_roles')->where('role_id', $role->id)->delete();
        DB::table('role_has_permissions')->where('role_id', $role->id)->delete();

        $role->delete();
    }

    public function down(): void
    {
        Role::query()->firstOrCreate([
            'name' => 'Merchant Support',
            'guard_name' => 'web',
        ]);
    }
};
