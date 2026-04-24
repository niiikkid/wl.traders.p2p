<?php

namespace App\Jobs;

use App\Contracts\TelegramServiceContract;
use App\Models\User;
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
        public int $userId,
        public string $title,
        public string $body
    ) {}

    public function handle(TelegramServiceContract $telegramService): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        try {
            $telegramService->sendNotification($user, $this->title, $this->body);
        } catch (\Throwable $e) {
            Log::warning('Notification delivery failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
