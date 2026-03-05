<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Создаем роль Team Leader (Тимлидер)
        Role::create(['name' => 'Team Leader']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем роль при откате миграции
        $role = Role::where('name', 'Team Leader')->first();
        if ($role) {
            $role->delete();
        }
    }
};
