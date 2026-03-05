<?php

namespace App\Http\Controllers\API\APP;

use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\UserDevice;
use Illuminate\Http\Request;

class StateController extends Controller
{
    public function index(Request $request)
    {
        $device = services()->device()->get($request->header('Access-Token'));

        if (!$device->android_id) {
            return response()->failWithMessage('Устройство не подключено', 401);
        }

        services()->device()->ping($device);

        $user = $device->user;
        $queryDispute = Dispute::query()
            ->whereRelation('order.paymentDetail', 'user_id', $user->id)
            ->where('status', DisputeStatus::PENDING);

        $cacheKey = "user_{$user->id}_pending_disputes";

        $latestDisputeAt = cache()->remember("latest_$cacheKey", now()->addSeconds(10), function () use ($queryDispute) {
            return $queryDispute->clone()
                ->latest('created_at')
                ->first('created_at')
                ?->created_at
                ?->timestamp;
        });

        $oldestDisputeAt = cache()->remember("oldest_$cacheKey", now()->addSeconds(10), function () use ($queryDispute) {
            return $queryDispute->clone()
                ->oldest('created_at')
                ->first('created_at')
                ?->created_at
                ?->timestamp;
        });

        $disputeCount = cache()->remember("count_$cacheKey", now()->addSeconds(10), function () use ($queryDispute) {
            return $queryDispute->clone()->count();
        });

        return [
            'pending_disputes' => [
                'latest_at' => $latestDisputeAt,
                'oldest_at' => $oldestDisputeAt,
                'count' => $disputeCount,
            ]
        ];
    }
}
