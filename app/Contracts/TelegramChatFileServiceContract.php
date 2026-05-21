<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\TelegramChatBotException;
use App\Models\TelegramChatMessage;
use App\Models\TelegramChatMessageAttachment;
use App\Services\Telegram\TelegramAttachmentReference;
use Illuminate\Http\UploadedFile;

interface TelegramChatFileServiceContract
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function extractAttachmentReference(array $rawPayload): ?TelegramAttachmentReference;

    /**
     * @throws TelegramChatBotException
     */
    public function downloadAndStore(
        TelegramChatMessage $message,
        TelegramAttachmentReference $reference,
    ): TelegramChatMessageAttachment;

    public function toUploadedFile(TelegramChatMessageAttachment $attachment): UploadedFile;

    public function deleteStoredFile(TelegramChatMessageAttachment $attachment): void;
}
