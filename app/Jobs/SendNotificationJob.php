<?php

namespace App\Jobs;

use App\Enums\NotificationDeliveryStatus;
use App\Models\Notification;
use App\Services\Notification\Channels\NotificationChannelFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $notificationId
    ) {}

    public function handle(NotificationChannelFactory $factory): void
    {
        $notification = Notification::query()->find($this->notificationId);

        if (! $notification) {
            return;
        }

        try {
            $channel = $factory->make($notification->channel);
            $channel->send($notification);

            $notification->update([
                'status' => NotificationDeliveryStatus::DELIVERED,
                'delivered_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Notification delivery failed', [
                'notification_id' => $notification->id,
                'channel' => $notification->channel->value,
                'error' => $e->getMessage(),
            ]);

            $notification->update([
                'status' => NotificationDeliveryStatus::FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
