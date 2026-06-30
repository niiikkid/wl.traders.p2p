<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserOnlinePing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserOnlinePingController extends Controller
{
    /**
     * Максимальная глубина истории онлайна (совпадает с ретеншеном пингов).
     */
    private const MAX_RANGE_DAYS = 7;

    /**
     * Вернуть присутствующие 15-секундные бакеты онлайна пользователя в диапазоне.
     *
     * Диапазон передаётся фронтендом в абсолютных секундах epoch (UTC),
     * посчитанных в таймзоне пользователя. Бэкенд оперирует только epoch,
     * поэтому проблем с таймзонами нет — конвертация и разметка по дням
     * происходят на фронтенде.
     */
    public function index(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'integer', 'min:0'],
            'to' => ['required', 'integer', 'gt:from'],
        ]);

        $step = UserOnlinePing::STEP_SECONDS;

        $from = (int) $validated['from'];
        $to = (int) $validated['to'];

        // Ограничиваем окно запроса максимум 7 сутками + небольшой буфер.
        $maxSpan = self::MAX_RANGE_DAYS * 86400 + 86400;
        if ($to - $from > $maxSpan) {
            $from = $to - $maxSpan;
        }

        $fromBucket = intdiv($from, $step);
        $toBucket = intdiv($to, $step);

        $buckets = UserOnlinePing::query()
            ->where('user_id', $user->id)
            ->whereBetween('bucket_15s', [$fromBucket, $toBucket])
            ->orderBy('bucket_15s')
            ->pluck('bucket_15s')
            ->map(static fn ($bucket): int => (int) $bucket)
            ->all();

        return response()->success([
            'step' => $step,
            'from' => $fromBucket * $step,
            'to' => $toBucket * $step,
            'buckets' => $buckets,
        ]);
    }
}
