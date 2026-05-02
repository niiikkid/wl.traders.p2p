<?php

namespace App\Http\Requests\API\V2\Dispute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

class StoreRequest extends FormRequest
{
    private const int MAX_RECEIPTS_COUNT = 3;

    private const int MAX_RECEIPT_SIZE_KB = 5120;

    private const int MAX_BASE64_RECEIPT_LENGTH = 6990508;

    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receipts' => ['required', 'array', 'max:3'],
            'receipts.*' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,pdf',
                'max:5120',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $receipts = (array) $this->input('receipts', []);

        if (count($receipts) > self::MAX_RECEIPTS_COUNT) {
            return;
        }

        $receipts = collect($receipts)
            ->map(function ($receipt) {
                if (! is_string($receipt) || mb_strlen($receipt) > self::MAX_BASE64_RECEIPT_LENGTH) {
                    return $receipt;
                }

                $fileData = base64_decode($receipt, true);

                if ($fileData === false) {
                    return $receipt;
                }

                if (strlen($fileData) > self::MAX_RECEIPT_SIZE_KB * 1024) {
                    return $receipt;
                }

                $tmpFilePath = sys_get_temp_dir().'/'.Str::uuid()->toString();
                file_put_contents($tmpFilePath, $fileData);
                $this->temporaryFiles[] = $tmpFilePath;

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

        if ($this->temporaryFiles !== []) {
            register_shutdown_function(function (): void {
                foreach ($this->temporaryFiles as $temporaryFile) {
                    if (is_file($temporaryFile)) {
                        @unlink($temporaryFile);
                    }
                }
            });
        }

        $this->merge([
            'receipts' => $receipts,
        ]);
    }
}
