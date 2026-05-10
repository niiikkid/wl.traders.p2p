<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Role::findOrCreate('Agent');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::query()
            ->where('name', 'Agent')
            ->whereDoesntHave('users')
            ->delete();
    }
};
