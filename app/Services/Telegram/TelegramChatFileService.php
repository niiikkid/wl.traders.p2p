<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Contracts\TelegramChatFileServiceContract;
use App\Exceptions\TelegramChatBotException;
use App\Models\TelegramChatMessage;
use App\Models\TelegramChatMessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

class TelegramChatFileService implements TelegramChatFileServiceContract
{
    private const STORAGE_DIRECTORY = 'telegram-chat-attachments';

    private const MAX_FILE_SIZE_BYTES = 5 * 1024 * 1024;

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function extractAttachmentReference(array $rawPayload): ?TelegramAttachmentReference
    {
        $photo = $rawPayload['photo'] ?? null;

        if (is_array($photo) && $photo !== []) {
            return $this->referenceFromPhoto($photo);
        }

        $document = $rawPayload['document'] ?? null;

        if (is_array($document)) {
            return $this->referenceFromDocument($document);
        }

        return null;
    }

    public function downloadAndStore(
        TelegramChatMessage $message,
        TelegramAttachmentReference $reference,
    ): TelegramChatMessageAttachment {
        if ($reference->fileSize !== null && $reference->fileSize > self::MAX_FILE_SIZE_BYTES) {
            throw new TelegramChatBotException('Размер файла превышает 5 МБ.');
        }

        $downloadedPath = services()->telegramChatBot()->downloadFileToPath($reference->fileId);

        try {
            $fileSize = (int) filesize($downloadedPath);

            if ($fileSize > self::MAX_FILE_SIZE_BYTES) {
                throw new TelegramChatBotException('Размер файла превышает 5 МБ.');
            }

            $uploadedFile = $this->buildUploadedFile($downloadedPath, $reference->originalName);
            $this->assertValidReceiptFile($uploadedFile);

            $extension = strtolower($uploadedFile->getClientOriginalExtension());
            $storedName = Str::lower(Str::random(32)).'.'.$extension;
            $storagePath = self::STORAGE_DIRECTORY.'/'.$storedName;

            Storage::disk('local')->makeDirectory(self::STORAGE_DIRECTORY);
            Storage::disk('local')->put($storagePath, (string) file_get_contents($downloadedPath));

            return TelegramChatMessageAttachment::query()->create([
                'telegram_chat_message_id' => $message->id,
                'telegram_file_id' => $reference->fileId,
                'telegram_file_unique_id' => $reference->fileUniqueId,
                'original_name' => $reference->originalName,
                'stored_name' => $storedName,
                'mime_type' => $uploadedFile->getMimeType() ?? 'application/octet-stream',
                'extension' => $extension,
                'size' => $fileSize,
                'storage_path' => $storagePath,
            ]);
        } finally {
            if (is_file($downloadedPath)) {
                @unlink($downloadedPath);
            }
        }
    }

    public function toUploadedFile(TelegramChatMessageAttachment $attachment): UploadedFile
    {
        $absolutePath = Storage::disk('local')->path($attachment->storage_path);

        if (! is_file($absolutePath)) {
            throw new TelegramChatBotException('Файл вложения не найден в хранилище.');
        }

        $file = new File($absolutePath);

        return new UploadedFile(
            $file->getPathname(),
            $attachment->stored_name,
            $attachment->mime_type,
            null,
            true,
        );
    }

    public function deleteStoredFile(TelegramChatMessageAttachment $attachment): bool
    {
        $storagePath = $attachment->storage_path;

        if ($storagePath === '' || ! $this->isAllowedStoragePath($storagePath)) {
            Log::warning('Skipped Telegram attachment file deletion: invalid storage path', [
                'telegram_chat_message_attachment_id' => $attachment->id,
                'storage_path' => $storagePath,
            ]);

            return false;
        }

        if (! Storage::disk('local')->exists($storagePath)) {
            return false;
        }

        Storage::disk('local')->delete($storagePath);

        return true;
    }

    private function isAllowedStoragePath(string $storagePath): bool
    {
        if (str_contains($storagePath, '..')) {
            return false;
        }

        return str_starts_with($storagePath, self::STORAGE_DIRECTORY.'/');
    }

    /**
     * @param  array<int, array<string, mixed>>  $photoSizes
     */
    private function referenceFromPhoto(array $photoSizes): ?TelegramAttachmentReference
    {
        $largest = null;
        $largestSize = -1;

        foreach ($photoSizes as $size) {
            if (! is_array($size)) {
                continue;
            }

            $fileId = $size['file_id'] ?? null;

            if (! is_string($fileId) || $fileId === '') {
                continue;
            }

            $fileSize = is_int($size['file_size'] ?? null) ? $size['file_size'] : -1;

            if ($fileSize >= $largestSize) {
                $largestSize = $fileSize;
                $largest = $size;
            }
        }

        if ($largest === null) {
            return null;
        }

        return new TelegramAttachmentReference(
            fileId: (string) $largest['file_id'],
            fileUniqueId: $this->nullableString($largest['file_unique_id'] ?? null),
            originalName: null,
            mimeType: 'image/jpeg',
            fileSize: $largestSize >= 0 ? $largestSize : null,
        );
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function referenceFromDocument(array $document): ?TelegramAttachmentReference
    {
        $fileId = $document['file_id'] ?? null;

        if (! is_string($fileId) || $fileId === '') {
            return null;
        }

        $fileSize = $document['file_size'] ?? null;

        return new TelegramAttachmentReference(
            fileId: $fileId,
            fileUniqueId: $this->nullableString($document['file_unique_id'] ?? null),
            originalName: $this->nullableString($document['file_name'] ?? null),
            mimeType: $this->nullableString($document['mime_type'] ?? null),
            fileSize: is_int($fileSize) ? $fileSize : null,
        );
    }

    private function buildUploadedFile(string $path, ?string $originalName): UploadedFile
    {
        $file = new File($path);
        $clientName = $originalName ?? basename($path);

        return new UploadedFile(
            $file->getPathname(),
            $clientName,
            $file->getMimeType(),
            null,
            true,
        );
    }

    private function assertValidReceiptFile(UploadedFile $uploadedFile): void
    {
        $validator = Validator::make(
            ['file' => $uploadedFile],
            [
                'file' => ['required', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            ],
        );

        if ($validator->fails()) {
            throw new TelegramChatBotException('Недопустимый тип или размер файла чека.');
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
