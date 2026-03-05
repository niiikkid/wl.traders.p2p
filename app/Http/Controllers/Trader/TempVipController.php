<?php

namespace App\Http\Controllers\Trader;

use App\Http\Controllers\Controller;
use App\Jobs\TempVipExpireJob;
use App\Models\UserTempVipActivation;
use App\Utils\Transaction;
use Illuminate\Http\Request;

class TempVipController extends Controller
{
    public function activate(Request $request)
    {
        if (! services()->settings()->isTempVipEnabled()) {
            return $this->respondError($request, 'Функционал временного VIP отключен администратором.');
        }

        $user = $request->user();

        if ($user->is_vip) {
            return $this->respondError($request, 'Постоянный VIP уже активен.');
        }

        if ($user->temp_vip_active_until && now()->lt($user->temp_vip_active_until)) {
            return $this->respondError($request, 'Временный VIP уже активирован.');
        }

        $progress = $user->getTempVipProgressData();

        if (! ($progress['can_activate'] ?? false)) {
            return $this->respondError($request, 'Норма сделок для активации временного VIP не выполнена.');
        }

        $duration = services()->settings()->getTempVipDurationMinutes();
        $activeUntil = now()->addMinutes($duration);

        Transaction::run(function () use ($user, $activeUntil) {
            $user->update([
                'temp_vip_active_until' => $activeUntil,
                'temp_vip_can_activate' => false,
            ]);

            UserTempVipActivation::create([
                'user_id' => $user->id,
                'activated_at' => now(),
                'expires_at' => $activeUntil,
            ]);

            services()->paymentDetail()->restoreVipLimitsForUser($user);
        });

        TempVipExpireJob::dispatch($user)->delay($activeUntil);

        return $request->expectsJson()
            ? response()->json([
                'success' => true,
                'active_until' => $activeUntil->toIso8601String(),
            ])
            : redirect()->back()->with('message', 'Временный VIP активирован.');
    }

    private function respondError(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        return redirect()->back()->with('error', $message);
    }
}

