<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NAMED_TRACKS = [
        'DreamsAreMessagesFromTheDeep.mp3',
        'LetWealthCome.mp3',
        'Loshadka-1.mp3',
        'Loshadka-2.mp3',
        'MoneyPowerWomanDrugs.mp3',
        'Pressure.mp3',
        'SixDays.mp3',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    $randomTrack = self::NAMED_TRACKS[array_rand(self::NAMED_TRACKS)];

                    $metaExists = DB::table('user_metas')
                        ->where('user_id', $user->id)
                        ->exists();

                    if ($metaExists) {
                        DB::table('user_metas')
                            ->where('user_id', $user->id)
                            ->update([
                                'notification_sound_track' => $randomTrack,
                            ]);

                        continue;
                    }

                    DB::table('user_metas')->insert([
                        'user_id' => $user->id,
                        'notification_sound_enabled' => true,
                        'notification_sound_track' => $randomTrack,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('user_metas')
            ->whereIn('notification_sound_track', self::NAMED_TRACKS)
            ->update([
                'notification_sound_track' => null,
            ]);
    }
};
