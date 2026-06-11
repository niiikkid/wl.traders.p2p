<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\ShadowSmsLog;

use Illuminate\Foundation\Http\FormRequest;

class DestroyByPatternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pattern' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pattern' => 'строка поиска',
        ];
    }
}
