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
        \App\Models\User::query()
            ->whereNull('avatar_uuid')
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    $user->update([
                        'avatar_uuid' => $user->email,
                        'avatar_style' => 'adventurer'
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
