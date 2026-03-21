<?php

declare(strict_types=1);

namespace App\Services\UserOnline;

use App\Models\UserOnlinePeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserOnlinePeriodRecorder
{
    private const TOUCH_BUCKET_SECONDS = 60;
    private const CONTINUOUS_SESSION_GAP_SECONDS = 600;

    public function touch(int $user_id, ?Carbon $occurred_at = null): void
    {
        $occurred_at = $occurred_at ?? now();
        $bucket_key = $this->makeBucketKey($user_id, $occurred_at);

        if (cache()->has($bucket_key)) {
            return;
        }

        DB::transaction(function () use ($user_id, $occurred_at) {
            $last_period = UserOnlinePeriod::query()
                ->where('user_id', $user_id)
                ->latest('ended_at')
                ->lockForUpdate()
                ->first();

            if (! $last_period) {
                UserOnlinePeriod::query()->create([
                    'user_id' => $user_id,
                    'started_at' => $occurred_at,
                    'ended_at' => $occurred_at,
                ]);

                return;
            }

            $gap = $last_period->ended_at->diffInSeconds($occurred_at, false);

            if ($gap >= 0 && $gap <= self::CONTINUOUS_SESSION_GAP_SECONDS) {
                if ($occurred_at->gt($last_period->ended_at)) {
                    $last_period->update([
                        'ended_at' => $occurred_at,
                    ]);
                }

                return;
            }

            UserOnlinePeriod::query()->create([
                'user_id' => $user_id,
                'started_at' => $occurred_at,
                'ended_at' => $occurred_at,
            ]);
        });

        cache()->put($bucket_key, true, now()->addSeconds(self::TOUCH_BUCKET_SECONDS + 5));
    }

    private function makeBucketKey(int $user_id, Carbon $occurred_at): string
    {
        return 'user-online-period-touch:' . $user_id . ':' . (int) floor($occurred_at->timestamp / self::TOUCH_BUCKET_SECONDS);
    }
}
