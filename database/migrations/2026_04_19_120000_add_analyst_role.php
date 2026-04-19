<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Role::query()->firstOrCreate(['name' => 'Analyst', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        Role::query()->where('name', 'Analyst')->where('guard_name', 'web')->delete();
    }
};
