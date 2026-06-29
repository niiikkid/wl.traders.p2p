<?php

namespace App\Jobs;

use App\Models\User;
use App\Utils\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateUserAvatarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        private readonly int $userId,
    ) {
        $this->afterCommit();
        $this->onQueue('avatar-generation');
    }

    public function handle(): void
    {
        $shouldGenerate = Transaction::run(function (): bool {
            $user = User::query()
                ->where('id', $this->userId)
                ->lockForUpdate()
                ->first();

            if ($user === null || $user->avatar_path !== null) {
                return false;
            }

            if ($user->avatar_generation_status !== 'generating') {
                return false;
            }

            $user->update([
                'avatar_generation_status' => 'processing',
            ]);

            return true;
        });

        if (! $shouldGenerate) {
            return;
        }

        $user = User::query()->with('roles')->find($this->userId);

        if ($user === null || $user->avatar_path !== null) {
            return;
        }

        try {
            services()->user()->regenerateAvatar($user);
        } catch (Throwable $exception) {
            User::query()
                ->where('id', $this->userId)
                ->whereNull('avatar_path')
                ->update([
                    'avatar_generation_status' => 'failed',
                    'avatar_generation_failed_at' => now(),
                    'avatar_generation_error' => mb_substr($exception->getMessage(), 0, 255),
                ]);

            Log::warning('Avatar generation job failed', [
                'user_id' => $this->userId,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
