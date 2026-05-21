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

    /**
     * Deletes the attachment file only when it lives under the Telegram private storage directory.
     *
     * @return bool True when a file was deleted from disk.
     */
    public function deleteStoredFile(TelegramChatMessageAttachment $attachment): bool;
}
