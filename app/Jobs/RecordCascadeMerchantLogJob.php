<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CascadeMerchantLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;

class RecordCascadeMerchantLogJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<string, mixed>
     */
    private array $attributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes)
    {
        $this->attributes = self::sanitizeAttributes($attributes);
        $this->afterCommit();
        $this->onQueue('callback');
    }

    public function handle(): void
    {
        CascadeMerchantLog::query()->create($this->attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function sanitizeAttributes(array $attributes): array
    {
        return array_map(fn (mixed $value) => self::sanitizeValue($value), $attributes);
    }

    private static function sanitizeValue(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return self::uploadedFileToLogStub($value);
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item) => self::sanitizeValue($item), $value);
        }

        return $value;
    }

    /**
     * @return array{_logged_as: string, original_name: string, size: ?int, mime_type: ?string}
     */
    private static function uploadedFileToLogStub(UploadedFile $file): array
    {
        $path = $file->getRealPath() ?: $file->getPathname();
        $size = null;
        $mime = $file->getClientMimeType();

        if ($path !== '' && is_file($path)) {
            $statSize = @filesize($path);
            $size = $statSize !== false ? $statSize : null;

            try {
                $detected = $file->getMimeType();
                if ($detected !== '') {
                    $mime = $detected;
                }
            } catch (\Throwable) {
            }
        }

        return [
            '_logged_as' => 'uploaded_file',
            'original_name' => $file->getClientOriginalName(),
            'size' => $size,
            'mime_type' => $mime !== '' ? $mime : null,
        ];
    }
}
