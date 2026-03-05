<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\UserDevice;
use App\Models\UserDevicePing;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class UserDevicePingController extends Controller
{
    /**
     * Возвращает историю пингов за последний час с шагом 5 секунд.
     */
    public function index(Request $request, UserDevice $device)
    {
        //$this->authorize('view', $device);
        if (auth()->id() !== $device->user_id || auth()->user()?->can_work_without_device) {
            abort(403);
        }

        $now = CarbonImmutable::now();
        $currentBucket = UserDevicePing::toBucket5s($now);
        // Ровно 720 ячеек: последние 60 минут, включая текущий бакет
        $startBucket = $currentBucket - 719;

        $pings = UserDevicePing::query()
            ->where('user_device_id', $device->id)
            ->whereBetween('bucket_5s', [$startBucket, $currentBucket])
            ->pluck('bucket_5s')
            ->all();

        $present = array_fill_keys($pings, true);

        $result = [];
        for ($bucket = $startBucket; $bucket <= $currentBucket; $bucket++) {
            $result[] = [
                'bucket' => $bucket,
                'ts' => $bucket * 5,
                'ok' => isset($present[$bucket]),
            ];
        }

        return response()->success([
            'items' => array_reverse($result),
            'from' => $startBucket * 5,
            'to' => $currentBucket * 5,
            'step' => 5,
        ]);
    }
}


