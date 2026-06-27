<?php

namespace App\Http\Controllers\API\APP;

use App\DTO\SMS\SmsDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\SMS\StoreRequest;
use App\Jobs\HandleSmsJob;
use App\Models\SenderStopList;
use App\Models\UserDevice;
use App\Services\Sms\Parser;
use App\Services\Sms\Utils\NormalizeMessage;
use Illuminate\Foundation\Bus\PendingDispatch;
use Throwable;

class SmsController extends Controller
{
    private const MAX_INCOMING_SMS_MESSAGE_LENGTH = 200;

    public function store(StoreRequest $request)
    {
        $androidAppVersion = $request->input('android_app_version');

        if (! is_string($androidAppVersion) || $androidAppVersion !== config('api.android_app_version')) {
            return response()->success();
        }

        $device = $request->get('device');
        if (! $device instanceof UserDevice) {
            $device = services()->device()->get($request->header('Access-Token'));
        }

        if (! $device->android_id) {
            return response()->failWithMessage('Устройство не подключено', 401);
        }

        /** @var PendingDispatch $dispatch */
        $dispatch = dispatch(function () use ($device): void {
            try {
                services()->device()->ping($device);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
        $dispatch->afterResponse();

        if (mb_strlen($request->message) > self::MAX_INCOMING_SMS_MESSAGE_LENGTH) {
            return response()->success();
        }

        $sender = NormalizeMessage::normalize($request->sender);
        $parser = new Parser;

        // Получаем список отправителей из кеша или базы данных
        $senderStopList = cache()->remember('sender_stop_list', now()->addMinutes(10), function () {
            return SenderStopList::query()->get('sender')->pluck('sender')->toArray();
        });

        if (in_array($sender, $senderStopList)) {
            return response()->success();
        }

        if ($parser->findMatchedStopWord($request->message) !== null) {
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
