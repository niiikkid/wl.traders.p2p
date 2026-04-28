<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate('Provider Liquidity');
    }

    public function down(): void
    {
        Role::query()
            ->where('name', 'Provider Liquidity')
            ->whereDoesntHave('users')
            ->delete();
    }
};
