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

class SendTelegramTraderTeamDisputeNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $disputeId,
    ) {
        $this->afterCommit();
        $this->onQueue('telegram-chat-automation');
    }

    public function handle(
        TelegramChatBotServiceContract $telegramChatBot,
        TelegramTraderTeamDisputeNotificationService $notificationService,
    ): void {
        $dispute = Dispute::query()
            ->with('order')
            ->find($this->disputeId);

        if ($dispute === null || $dispute->order === null) {
            return;
        }

        if (! $notificationService->isDisputePending($dispute)) {
            return;
        }

        $notificationService->sendNotifications(
            $dispute,
            TelegramTraderTeamDisputeNotificationType::IMMEDIATE,
            $telegramChatBot,
        );

        SendTelegramTraderTeamDisputeReminderJob::dispatch(
            $dispute->id,
            TelegramTraderTeamDisputeNotificationType::FIFTEEN_MINUTE_REMINDER,
        )->delay(now()->addMinutes(15));
    }
}
