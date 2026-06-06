<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\TelegramChatBotServiceContract;
use App\Enums\TelegramTraderTeamDisputeNotificationType;
use App\Models\Dispute;
use App\Services\Telegram\TelegramTraderTeamDisputeNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTelegramTraderTeamDisputeReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $disputeId,
        private readonly TelegramTraderTeamDisputeNotificationType $reminderType,
    ) {
        $this->afterCommit();
        $this->onQueue('telegram-chat-automation');
    }

    public function handle(
        TelegramChatBotServiceContract $telegramChatBot,
        TelegramTraderTeamDisputeNotificationService $notificationService,
    ): void {
        if (! $this->reminderType->equals(TelegramTraderTeamDisputeNotificationType::FIFTEEN_MINUTE_REMINDER)
            && ! $this->reminderType->equals(TelegramTraderTeamDisputeNotificationType::HOURLY_REMINDER)) {
            return;
        }

        $dispute = Dispute::query()
            ->with('order')
            ->find($this->disputeId);

        if ($dispute === null || $dispute->order === null) {
            return;
        }

        if (! $notificationService->isDisputePending($dispute)) {
            return;
        }

        $notificationService->sendNotifications($dispute, $this->reminderType, $telegramChatBot);

        self::dispatch(
            $dispute->id,
            TelegramTraderTeamDisputeNotificationType::HOURLY_REMINDER,
        )->delay(now()->addHour());
    }
}
