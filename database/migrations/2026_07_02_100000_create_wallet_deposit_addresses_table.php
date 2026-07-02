<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_deposit_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('currency')->default('USDT');
            $table->string('network')->default('trx');
            $table->string('address');
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('balance_units')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['currency', 'network', 'address'], 'wallet_deposit_addresses_unique');
            $table->index(['currency', 'network', 'is_active'], 'wallet_deposit_addresses_pool_index');
            $table->index('last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_deposit_addresses');
    }
};
