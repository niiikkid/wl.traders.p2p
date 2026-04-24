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
        if (Schema::hasTable('notifications')) {
            Schema::drop('notifications');
        }

        if (Schema::hasTable('notification_rules') && Schema::hasColumn('notification_rules', 'channels')) {
            Schema::table('notification_rules', function (Blueprint $table) {
                $table->dropColumn('channels');
            });
        }

        if (Schema::hasTable('user_metas')) {
            $columnsToDrop = [];

            if (Schema::hasColumn('user_metas', 'notification_sound_enabled')) {
                $columnsToDrop[] = 'notification_sound_enabled';
            }

            if (Schema::hasColumn('user_metas', 'notification_sound_track')) {
                $columnsToDrop[] = 'notification_sound_track';
            }

            if (! empty($columnsToDrop)) {
                Schema::table('user_metas', function (Blueprint $table) use ($columnsToDrop) {
                    $table->dropColumn($columnsToDrop);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('event', 100);
                $table->string('channel', 30);
                $table->string('status', 30);
                $table->string('title');
                $table->text('body');
                $table->json('payload')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'channel', 'status']);
                $table->index(['user_id', 'read_at']);
            });
        }

        if (Schema::hasTable('notification_rules') && ! Schema::hasColumn('notification_rules', 'channels')) {
            Schema::table('notification_rules', function (Blueprint $table) {
                $table->json('channels')->nullable()->after('statuses');
            });
        }

        if (Schema::hasTable('user_metas')) {
            $hasNotificationSoundEnabled = Schema::hasColumn('user_metas', 'notification_sound_enabled');
            $hasNotificationSoundTrack = Schema::hasColumn('user_metas', 'notification_sound_track');

            if (! $hasNotificationSoundEnabled || ! $hasNotificationSoundTrack) {
                Schema::table('user_metas', function (Blueprint $table) use ($hasNotificationSoundEnabled, $hasNotificationSoundTrack) {
                    if (! $hasNotificationSoundEnabled) {
                        $table->boolean('notification_sound_enabled')
                            ->default(true)
                            ->after('allowed_categories');
                    }

                    if (! $hasNotificationSoundTrack) {
                        $table->string('notification_sound_track')
                            ->nullable()
                            ->after('notification_sound_enabled');
                    }
                });
            }
        }
    }
};
