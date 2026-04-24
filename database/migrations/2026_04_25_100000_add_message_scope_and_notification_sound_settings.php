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
        if (Schema::hasTable('notification_rules') && ! Schema::hasColumn('notification_rules', 'message_scope')) {
            Schema::table('notification_rules', function (Blueprint $table) {
                $table->string('message_scope', 32)->nullable()->after('statuses');
            });
        }

        if (Schema::hasTable('user_metas')) {
            Schema::table('user_metas', function (Blueprint $table) {
                if (! Schema::hasColumn('user_metas', 'notification_sound_order_enabled')) {
                    $table->boolean('notification_sound_order_enabled')
                        ->default(true)
                        ->after('allowed_categories');
                }

                if (! Schema::hasColumn('user_metas', 'notification_sound_order_track')) {
                    $table->string('notification_sound_order_track')
                        ->nullable()
                        ->after('notification_sound_order_enabled');
                }

                if (! Schema::hasColumn('user_metas', 'notification_sound_dispute_enabled')) {
                    $table->boolean('notification_sound_dispute_enabled')
                        ->default(true)
                        ->after('notification_sound_order_track');
                }

                if (! Schema::hasColumn('user_metas', 'notification_sound_dispute_track')) {
                    $table->string('notification_sound_dispute_track')
                        ->nullable()
                        ->after('notification_sound_dispute_enabled');
                }

                if (! Schema::hasColumn('user_metas', 'notification_sound_message_enabled')) {
                    $table->boolean('notification_sound_message_enabled')
                        ->default(true)
                        ->after('notification_sound_dispute_track');
                }

                if (! Schema::hasColumn('user_metas', 'notification_sound_message_track')) {
                    $table->string('notification_sound_message_track')
                        ->nullable()
                        ->after('notification_sound_message_enabled');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('notification_rules') && Schema::hasColumn('notification_rules', 'message_scope')) {
            Schema::table('notification_rules', function (Blueprint $table) {
                $table->dropColumn('message_scope');
            });
        }

        if (Schema::hasTable('user_metas')) {
            $columnsToDrop = [];

            foreach ([
                'notification_sound_order_enabled',
                'notification_sound_order_track',
                'notification_sound_dispute_enabled',
                'notification_sound_dispute_track',
                'notification_sound_message_enabled',
                'notification_sound_message_track',
            ] as $column) {
                if (Schema::hasColumn('user_metas', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (! empty($columnsToDrop)) {
                Schema::table('user_metas', function (Blueprint $table) use ($columnsToDrop) {
                    $table->dropColumn($columnsToDrop);
                });
            }
        }
    }
};
