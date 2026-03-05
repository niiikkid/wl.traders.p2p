<?php

namespace App\Http\Requests\Admin\App;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'apk' => [
                'required',
                'file',
                'mimetypes:application/vnd.android.package-archive,application/zip,application/octet-stream',
                'max:20480',
                function (string $attribute, UploadedFile $file, Closure $fail) {
                    if (strtolower($file->getClientOriginalExtension()) !== 'apk') {
                        $fail('Файл должен иметь расширение .apk');
                    }
                },
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'apk' => 'файл приложения',
        ];
    }
}
