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
        \App\Models\PaymentDetail::query()
            ->whereNull('last_used_at')
            ->update(['last_used_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
