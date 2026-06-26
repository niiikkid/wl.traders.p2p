<?php

namespace App\Services\Sms;

use App\Contracts\SmsServiceContract;
use App\DTO\SMS\SmsDTO;
use App\Exceptions\SmsServiceException;
use App\Models\SmsLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Notification\Events\MessageReceivedNotificationEvent;
use App\Services\Sms\AutoClose\SmsAutoCloseService;
use Throwable;

class SmsService implements SmsServiceContract
{
    /**
     * @throws SmsServiceException
     */
    public function handleSms(SmsDTO $sms): void
    {
        $parser = new Parser;

        $device = cache()->remember(
            'user_device_'.$sms->deviceID,
            now()->addMinutes(10),
            function () use ($sms) {
                return UserDevice::query()
                    ->where('id', $sms->deviceID)
                    ->with(['user'])
                    ->first();
            }
        );
        $user = $device->user;

        $result = $parser->parse($sms->sender, $sms->message, $sms->type);
        $smsLog = $this->logSms($sms, $device, $user, $result);

        $smsLog->loadMissing('user', 'device');

        services()->notification()->dispatch(
            new MessageReceivedNotificationEvent($smsLog, null, null)
        );

        if ($user->sms_auto_close_orders_enabled && is_array($result)) {
            $this->autoCloseOrder($smsLog, $device, $user, $result);
        }
    }

    /**
     * Попытка безопасного автоматического закрытия сделки по поступлению.
     * Ошибки не должны прерывать приём сообщений.
     *
     * @param  array{operation_type: string, amount: string, card: ?string, balance: ?string, bank: ?string}  $result
     */
    protected function autoCloseOrder(SmsLog $smsLog, UserDevice $device, User $user, array $result): void
    {
        try {
            app(SmsAutoCloseService::class)->attempt($smsLog, $device, $user, $result);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array{operation_type: string, amount: string, card: ?string, balance: ?string, bank: ?string}|null  $parsingResult
     */
    protected function logSms(SmsDTO $sms, UserDevice $device, User $user, ?array $parsingResult): SmsLog
    {
        return SmsLog::create([
            'sender' => $sms->sender,
            'message' => $sms->message,
            'parsing_result' => $parsingResult,
            'timestamp' => $sms->timestamp / 1000,
            'type' => $sms->type,
            'user_device_id' => $device->id,
            'user_id' => $user->id,
        ]);
    }
}
