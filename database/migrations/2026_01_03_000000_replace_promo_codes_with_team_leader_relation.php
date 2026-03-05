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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'team_leader_id')) {
                $table->foreignId('team_leader_id')
                    ->nullable()
                    ->after('merchant_id')
                    ->constrained('users');
            }

            if (Schema::hasColumn('users', 'promo_code_id')) {
                $table->dropConstrainedForeignId('promo_code_id');
                //$table->dropColumn('promo_code_id'); //TODO ???
            }

            if (Schema::hasColumn('users', 'promo_used_at')) {
                $table->dropColumn('promo_used_at');
            }
        });

        Schema::dropIfExists('promo_codes');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->integer('max_uses')->default(0)->comment('0 - unlimited');
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('team_leader_id')->constrained('users');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'team_leader_id')) {
                $table->dropConstrainedForeignId('team_leader_id');
            }

            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->onDelete('set null');
            $table->timestamp('promo_used_at')->nullable()->after('promo_code_id');
        });
    }
};

