<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_deposit_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('wallet_id')->constrained('wallets');
            $table->string('balance_type');

            $table->foreignId('deposit_address_id')->nullable()->constrained('wallet_deposit_addresses')->nullOnDelete();
            $table->string('address');

            $table->string('currency')->default('USDT');
            $table->string('network')->default('trx');

            // Money amounts are stored as string integer units (see 25-money.mdc).
            $table->string('amount');
            $table->string('amount_received')->nullable();

            $table->string('status')->default('pending');

            $table->string('txid')->nullable();
            $table->unsignedInteger('confirmations')->default(0);
            $table->string('match_type')->nullable();
            $table->timestamp('matched_at')->nullable();

            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('poll_until_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('finalized_at')->nullable();

            $table->string('error_message')->nullable();

            $table->string('qr_disk')->nullable();
            $table->string('qr_path')->nullable();

            // Historical Invoice created on settlement (reuses the proven credit path).
            $table->foreignId('settled_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'poll_until_at'], 'wallet_deposit_invoices_polling_index');
            $table->index(['deposit_address_id', 'status'], 'wallet_deposit_invoices_address_status_index');
            $table->index('txid');
            $table->index('wallet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_deposit_invoices');
    }
};
