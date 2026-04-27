<?php

namespace App\Http\Requests\API\V2\Dispute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receipts' => ['required', 'array'],
            'receipts.*' => [
                'required',
                'mimes:jpeg,jpg,png,pdf',
                'max:5120',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $receipts = collect((array) $this->input('receipts', []))
            ->map(function ($receipt) {
                $fileData = base64_decode((string) $receipt);

                $tmpFilePath = sys_get_temp_dir().'/'.Str::uuid()->toString();
                file_put_contents($tmpFilePath, $fileData);

                $tmpFile = new File($tmpFilePath);

                return new UploadedFile(
                    $tmpFile->getPathname(),
                    $tmpFile->getFilename(),
                    $tmpFile->getMimeType(),
                    0,
                    true,
                );
            })
            ->values()
            ->all();

        $this->merge([
            'receipts' => $receipts,
        ]);
    }
}
