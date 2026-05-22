<?php

namespace App\Services\Sms;

use App\DTO\SMS\ShadowSmsLogData;
use App\Models\ShadowSmsLog;

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
}
