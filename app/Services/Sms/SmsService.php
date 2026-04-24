<?php

namespace App\Services\Sms;

use App\Contracts\SmsServiceContract;
use App\DTO\SMS\SmsDTO;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Exceptions\SmsServiceException;
use App\Models\Order;
use App\Models\SmsLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Notification\Events\MessageReceivedNotificationEvent;
use App\Services\Sms\Utils\NormalizeMessage;

class SmsService implements SmsServiceContract
{
    /**
     * @throws SmsServiceException
     */
    public function handleSms(SmsDTO $sms): void
    {
        $sender = $this->normalizeMessage($sms->sender);
        $parser = new Parser;

        $device = cache()->remember(
            'user_device_'.$sms->deviceID,
            now()->addMinutes(10),
            function () use ($sms) {
                return UserDevice::where('id', $sms->deviceID)->with('user')->first();
            }
        );
        $user = $device->user;

        $smsLog = $this->logSms($sms, $device, $user);

        $result = $parser->parse($sender, $sms->message);
        $paymentGateway = $result?->paymentGateway ?? $parser->getGatewayBySender($sender);

        /**
         * @var Order|null $order
         */
        $order = null;

        if (! empty($result)) {
            $order = queries()
                ->order()
                ->findPending($result->amount, $user, $result->paymentGateway, $device);
        }

        if ($order) {
            $smsLog->update([
                'order_id' => $order->id,
            ]);
        }

        $smsLog->loadMissing('user', 'device');
        $order?->loadMissing('paymentDetail');

        services()->notification()->dispatch(
            new MessageReceivedNotificationEvent($smsLog, $paymentGateway, $order)
        );

        if (! $order) {
            return;
        }

        if (! $user->sms_auto_close_orders_enabled) {
            return;
        }

        if ($order->status->equals(OrderStatus::PENDING)) {
            services()->order()->finishOrderAsSuccessful($order->id, OrderSubStatus::SUCCESSFULLY_PAID);
        }
    }

    protected function logSms(SmsDTO $sms, UserDevice $device, User $user): SmsLog
    {
        return SmsLog::create([
            'sender' => $this->normalizeMessage($sms->sender),
            'message' => $this->normalizeMessage($sms->message),
            'parsing_result' => (new Parser)->parseRaw($sms->message, $sms->sender),
            'timestamp' => $sms->timestamp / 1000,
            'type' => $sms->type,
            'user_device_id' => $device->id,
            'user_id' => $user->id,
        ]);
    }

    protected function normalizeMessage(string $message): string
    {
        return NormalizeMessage::normalize($message);
    }
}
