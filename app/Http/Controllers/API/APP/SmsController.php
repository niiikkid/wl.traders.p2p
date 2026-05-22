<?php

namespace App\Http\Controllers\API\APP;

use App\DTO\SMS\ShadowSmsLogData;
use App\DTO\SMS\SmsDTO;
use App\Enums\ShadowSmsLogFilterReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\SMS\StoreRequest;
use App\Jobs\HandleSmsJob;
use App\Jobs\RecordShadowSmsLogJob;
use App\Models\SenderStopList;
use App\Models\UserDevice;
use App\Services\Sms\Parser;
use App\Services\Sms\Utils\NormalizeMessage;
use Throwable;

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
            $this->recordShadowSmsLog(
                request: $request,
                device: $device,
                filterReason: ShadowSmsLogFilterReason::MaxMessageLength,
                messageLength: mb_strlen($request->message),
            );

            return response()->success();
        }

        $sender = NormalizeMessage::normalize($request->sender);
        $parser = new Parser;

        // Получаем список отправителей из кеша или базы данных
        $senderStopList = cache()->remember('sender_stop_list', now()->addMinutes(10), function () {
            return SenderStopList::query()->get('sender')->pluck('sender')->toArray();
        });

        if (in_array($sender, $senderStopList)) {
            $this->recordShadowSmsLog(
                request: $request,
                device: $device,
                filterReason: ShadowSmsLogFilterReason::SenderStopList,
                matchedSender: $sender,
            );

            return response()->success();
        }

        $matchedStopWord = $parser->findMatchedStopWord($request->message);

        if ($matchedStopWord !== null) {
            $this->recordShadowSmsLog(
                request: $request,
                device: $device,
                filterReason: ShadowSmsLogFilterReason::StopWord,
                matchedStopWord: $matchedStopWord,
            );

            return response()->success();
        }

        HandleSmsJob::dispatch(
            SmsDTO::fromArray($request->validated() + [
                'deviceID' => $device->id,
            ])
        );

        return response()->success();
    }

    private function recordShadowSmsLog(
        StoreRequest $request,
        UserDevice $device,
        ShadowSmsLogFilterReason $filterReason,
        ?string $matchedSender = null,
        ?string $matchedStopWord = null,
        ?int $messageLength = null,
    ): void {
        if (! services()->settings()->isShadowSmsLogEnabled()) {
            return;
        }

        try {
            RecordShadowSmsLogJob::dispatch(new ShadowSmsLogData(
                userId: $device->user_id,
                userDeviceId: $device->id,
                sender: $request->sender,
                message: $request->message,
                timestamp: $request->timestamp,
                type: $request->type,
                filterReason: $filterReason->value,
                matchedSender: $matchedSender,
                matchedStopWord: $matchedStopWord,
                messageLength: $messageLength,
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
