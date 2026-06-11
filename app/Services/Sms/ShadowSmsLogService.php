<?php

namespace App\Services\Sms;

use App\DTO\SMS\ShadowSmsLogData;
use App\Models\ShadowSmsLog;
use Illuminate\Database\Eloquent\Builder;

class ShadowSmsLogService
{
    public function create(ShadowSmsLogData $data): ShadowSmsLog
    {
        return ShadowSmsLog::query()->create([
            'user_id' => $data->userId,
            'user_device_id' => $data->userDeviceId,
            'sender' => $data->sender,
            'message' => $data->message,
            'timestamp' => $data->timestamp,
            'type' => $data->type,
            'filter_reason' => $data->filterReason,
            'matched_sender' => $data->matchedSender,
            'matched_stop_word' => $data->matchedStopWord,
            'message_length' => $data->messageLength,
        ]);
    }

    public function matchingPatternQuery(string $pattern): Builder
    {
        $like = '%'.addcslashes($pattern, '%_\\').'%';

        return ShadowSmsLog::query()->where(function (Builder $query) use ($like): void {
            $query
                ->where('sender', 'like', $like)
                ->orWhere('message', 'like', $like)
                ->orWhere('matched_sender', 'like', $like)
                ->orWhere('matched_stop_word', 'like', $like);
        });
    }

    public function deleteMatching(string $pattern): int
    {
        return $this->matchingPatternQuery($pattern)->delete();
    }
}
