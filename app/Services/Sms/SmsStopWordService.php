<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\SmsLog;

class SmsStopWordService
{
    public function __construct(
        private readonly Parser $parser,
    ) {}

    public function deleteUndefinedOperationLogsMatchingWord(string $normalizedStopWord): int
    {
        $deletedCount = 0;

        SmsLog::query()
            ->whereOperationTypeUndefined()
            ->select(['id', 'message'])
            ->chunkById(500, function ($logs) use ($normalizedStopWord, &$deletedCount): void {
                $idsToDelete = $logs
                    ->filter(fn (SmsLog $log): bool => $this->parser->matchesStopWord($log->message, $normalizedStopWord))
                    ->pluck('id');

                if ($idsToDelete->isEmpty()) {
                    return;
                }

                $deletedCount += SmsLog::query()->whereIn('id', $idsToDelete)->delete();
            });

        return $deletedCount;
    }
}
