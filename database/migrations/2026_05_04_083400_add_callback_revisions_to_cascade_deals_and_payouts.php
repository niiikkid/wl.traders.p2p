<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cascade_deals', function (Blueprint $table) {
            $table->unsignedBigInteger('callback_payload_revision')->default(0)->after('callback_url');
            $table->unsignedBigInteger('last_callback_delivered_revision')->default(0)->after('callback_payload_revision');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->unsignedBigInteger('callback_payload_revision')->default(0)->after('callback_url');
            $table->unsignedBigInteger('last_callback_delivered_revision')->default(0)->after('callback_payload_revision');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn([
                'callback_payload_revision',
                'last_callback_delivered_revision',
            ]);
        });

        Schema::table('cascade_deals', function (Blueprint $table) {
            $table->dropColumn([
                'callback_payload_revision',
                'last_callback_delivered_revision',
            ]);
        });
    }
};
