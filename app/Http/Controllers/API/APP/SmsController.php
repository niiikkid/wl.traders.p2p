<?php

namespace App\Http\Controllers\API\APP;

use App\DTO\SMS\SmsDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\SMS\StoreRequest;
use App\Jobs\HandleSmsJob;
use App\Models\SenderStopList;
use App\Services\Sms\Parser;
use App\Services\Sms\Utils\NormalizeMessage;

class SmsController extends Controller
{
    private const MAX_INCOMING_SMS_MESSAGE_LENGTH = 200;

    public function store(StoreRequest $request)
    {
        $device = services()->device()->get($request->header('Access-Token'));

        if (! $device->android_id) {
            return response()->failWithMessage('Устройство не подключено', 401);
        }

        services()->device()->ping($device);

        if (mb_strlen($request->message) > self::MAX_INCOMING_SMS_MESSAGE_LENGTH) {
            return response()->success();
        }

        $sender = NormalizeMessage::normalize($request->sender);

        // Получаем список отправителей из кеша или базы данных
        $senderStopList = cache()->remember('sender_stop_list', now()->addMinutes(10), function () {
            return SenderStopList::query()->get('sender')->pluck('sender')->toArray();
        });

        if (in_array($sender, $senderStopList)) {
            return response()->success();
        }

        if ((new Parser)->hasStopWord($request->message)) {
            return response()->success();
        }

        HandleSmsJob::dispatchSync(
            SmsDTO::fromArray($request->validated() + [
                'deviceID' => $device->id,
            ])
        );

        return response()->success();
    }
}
