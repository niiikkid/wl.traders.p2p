<?php

namespace App\Services\Sms;

use App\Contracts\SmsServiceContract;
use App\DTO\SMS\SmsDTO;
use App\Exceptions\SmsServiceException;
use App\Models\SmsLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Notification\Events\MessageReceivedNotificationEvent;

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

        $result = $parser->parse($sms->sender, $sms->message);
        $smsLog = $this->logSms($sms, $device, $user, $result);

        $order = null;

        $smsLog->loadMissing('user', 'device');

        services()->notification()->dispatch(
            new MessageReceivedNotificationEvent($smsLog, null, $order)
        );

        // TODO: Rework order matching for the new AI-based SMS parsing flow.
        // if (! empty($result)) {
        //     $order = queries()
        //         ->order()
        //         ->findPending($result->amount, $user, $result->paymentGateway, $device);
        // }
        //
        // if ($order) {
        //     $smsLog->update([
        //         'order_id' => $order->id,
        //     ]);
        // }
        //
        // if (! $order) {
        //     return;
        // }
        //
        // if (! $user->sms_auto_close_orders_enabled) {
        //     return;
        // }
        //
        // if ($order->status->equals(OrderStatus::PENDING)) {
        //     services()->order()->finishOrderAsSuccessful($order->id, OrderSubStatus::SUCCESSFULLY_PAID);
        // }
    }

    /**
     * @param  array{operation_type: string, amount: string, card: ?string, balance: ?string}|null  $parsingResult
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
