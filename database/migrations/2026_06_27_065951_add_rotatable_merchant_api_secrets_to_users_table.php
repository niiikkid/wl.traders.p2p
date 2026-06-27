<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_access_token_hash', 64)->nullable()->unique()->after('api_access_token');
            $table->text('webhook_secret')->nullable()->after('api_access_token_hash');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['api_access_token']);
        });

        DB::statement('ALTER TABLE users MODIFY api_access_token TEXT NULL');

        DB::table('users')
            ->whereNotNull('api_access_token')
            ->orderBy('id')
            ->eachById(function (object $user): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'api_access_token' => encrypt($user->api_access_token),
                        'api_access_token_hash' => hash('sha256', $user->api_access_token),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->whereNotNull('api_access_token')
            ->orderBy('id')
            ->eachById(function (object $user): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'api_access_token' => decrypt($user->api_access_token),
                    ]);
            });

        DB::statement('ALTER TABLE users MODIFY api_access_token VARCHAR(255) NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('api_access_token');
            $table->dropUnique(['api_access_token_hash']);
            $table->dropColumn(['api_access_token_hash', 'webhook_secret']);
        });
    }
};
