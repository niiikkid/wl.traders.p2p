<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppApkService
{
    private const APK_FILE_NAME = 'p2p-bridge.apk';
    private const UPDATED_AT_KEY = 'app_apk_updated_at';

    public function fileName(): string
    {
        return self::APK_FILE_NAME;
    }

    public function path(): string
    {
        return storage_path(self::APK_FILE_NAME);
    }

    public function exists(): bool
    {
        return file_exists($this->path());
    }

    public function downloadResponse(): BinaryFileResponse
    {
        abort_unless($this->exists(), 404, 'Приложение не найдено');

        return response()->file($this->path(), [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }

    public function replace(UploadedFile $file): void
    {
        $file->move(storage_path(), self::APK_FILE_NAME);

        $this->saveLastUploadedAt(now());
    }

    public function lastUploadedAt(): ?string
    {
        $value = Setting::query()->where('key', self::UPDATED_AT_KEY)->value('value');

        if ($value) {
            return $this->normalizeDate($value);
        }

        if ($this->exists()) {
            $fromFile = Carbon::createFromTimestamp(filemtime($this->path()));

            $this->saveLastUploadedAt($fromFile);

            return $fromFile->toDateTimeString();
        }

        return null;
    }

    private function saveLastUploadedAt(Carbon|string $date): void
    {
        $timestamp = $date instanceof Carbon ? $date->toDateTimeString() : Carbon::parse($date)->toDateTimeString();

        Setting::updateOrCreate(
            ['key' => self::UPDATED_AT_KEY],
            ['value' => $timestamp]
        );

        cache()->put('app-settings', Setting::all());
    }

    private function normalizeDate(string $value): string
    {
        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return $value;
        }
    }
}
