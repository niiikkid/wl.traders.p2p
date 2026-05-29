<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\PotentiallyTranslatedString;

class ReceiptFileRule implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('Файл не был загружен корректно.');

            return;
        }

        if (! $value->isValid()) {
            $fail('Файл поврежден или не был загружен.');

            return;
        }

        $standardValidator = Validator::make(
            [$attribute => $value],
            [$attribute => ['file', 'mimes:jpg,jpeg,png,pdf']]
        );

        if (! $standardValidator->fails()) {
            return;
        }

        if ($this->passesPdfFallback($value)) {
            return;
        }

        $fail('Допустимы только JPG, JPEG, PNG или PDF.');
    }

    public static function hasPdfSignature(string $path): bool
    {
        if (! is_file($path) || ! is_readable($path)) {
            return false;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            $header = fread($handle, 4096);
        } finally {
            fclose($handle);
        }

        return is_string($header) && str_contains($header, '%PDF-');
    }

    private function passesPdfFallback(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension !== 'pdf') {
            return false;
        }

        return self::hasPdfSignature($file->getPathname());
    }
}
