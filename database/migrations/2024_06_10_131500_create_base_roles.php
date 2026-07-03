<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Base roles that used to be seeded from the legacy SQL dump.
     * Kept idempotent so already-installed databases are not affected.
     */
    public function up(): void
    {
        foreach (['Super Admin', 'Trader', 'Merchant'] as $name) {
            Role::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};
