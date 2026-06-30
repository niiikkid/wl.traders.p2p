<?php

declare(strict_types=1);

namespace App\Services\UserOnline;

use App\Models\UserOnlinePing;
use Carbon\Carbon;

class UserOnlinePingRecorder
{
    /**
     * Зафиксировать онлайн-пинг пользователя в веб-панели.
     *
     * Хранение строго с шагом 15 секунд: на один 15-секундный бакет приходится
     * не более одной записи, как бы часто фронтенд ни присылал пинги.
     * Лёгкая защита через кеш, чтобы не делать лишних запросов в БД.
     */
    public function record(int $user_id, ?Carbon $occurred_at = null): void
    {
        $occurred_at = $occurred_at ?? now();
        $bucket = UserOnlinePing::toBucket15s($occurred_at);

        $guard_key = $this->makeGuardKey($user_id, $bucket);

        if (cache()->has($guard_key)) {
            return;
        }

        UserOnlinePing::query()->updateOrCreate(
            [
                'user_id' => $user_id,
                'bucket_15s' => $bucket,
            ],
            []
        );

        cache()->put($guard_key, true, now()->addSeconds(UserOnlinePing::STEP_SECONDS + 5));
    }

    private function makeGuardKey(int $user_id, int $bucket): string
    {
        return 'user-online-ping:'.$user_id.':'.$bucket;
    }
}
