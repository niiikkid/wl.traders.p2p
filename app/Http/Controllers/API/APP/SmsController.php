<?php

namespace App\Http\Controllers\API\APP;

use App\DTO\SMS\SmsDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\SMS\StoreRequest;
use App\Jobs\HandleSmsJob;
use App\Models\SenderStopList;
use App\Services\Sms\Utils\NormalizeMessage;

class SmsController extends Controller
{
    public function store(StoreRequest $request)
    {
        $device = services()->device()->get($request->header('Access-Token'));

        if (!$device->android_id) {
            return response()->failWithMessage('Устройство не подключено', 401);
        }

        services()->device()->ping($device);

        $sender = NormalizeMessage::normalize($request->sender);

        // Получаем список отправителей из кеша или базы данных
        $senderStopList = cache()->remember('sender_stop_list', now()->addMinutes(10), function () {
            return SenderStopList::query()->get('sender')->pluck('sender')->toArray();
        });

        if (in_array($sender, $senderStopList)) {
            return response()->success();
        }

        HandleSmsJob::dispatch(
            SmsDTO::fromArray($request->validated() + [
                    'deviceID' => $device->id,
                ])
        );

        return response()->success();
    }
}
