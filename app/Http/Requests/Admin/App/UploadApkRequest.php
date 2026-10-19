<?php

namespace App\Http\Requests\Admin\App;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class UploadApkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'apk' => [
                'bail',
                'required',
                'file',
                'mimetypes:application/vnd.android.package-archive,application/zip,application/octet-stream',
                'max:20480',
                function (string $attribute, UploadedFile $file, Closure $fail): void {
                    if (strtolower($file->getClientOriginalExtension()) !== 'apk') {
                        $fail('Файл должен иметь расширение .apk');

                        return;
                    }

                    if (! $this->hasApkStructure($file)) {
                        $fail('Файл должен быть APK-архивом с бинарным AndroidManifest.xml');
                    }
                },
            ],
        ];
    }

    private function hasApkStructure(UploadedFile $file): bool
    {
        if (! class_exists(ZipArchive::class)) {
            return false;
        }

        $archive = new ZipArchive;

        if ($archive->open($file->getPathname(), ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            return false;
        }

        try {
            $manifest = $archive->statName('AndroidManifest.xml');

            if ($manifest === false || $manifest['size'] <= 8) {
                return false;
            }

            $header = $archive->getFromName('AndroidManifest.xml', 8);

            if ($header === false || strlen($header) !== 8) {
                return false;
            }

            $chunk = unpack('vtype/vheaderSize/Vsize', $header);

            return $chunk['type'] === 0x0003
                && $chunk['headerSize'] === 8
                && $chunk['size'] === $manifest['size'];
        } finally {
            $archive->close();
        }
    }

    public function attributes(): array
    {
        return [
            'apk' => 'файл приложения',
        ];
    }
}
