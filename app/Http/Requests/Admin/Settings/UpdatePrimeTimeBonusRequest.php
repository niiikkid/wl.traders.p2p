<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrimeTimeBonusRequest extends FormRequest
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
            'starts' => ['required', 'string', 'date_format:H:i'],
            'ends' => ['required', 'string', 'date_format:H:i', 'after:starts'],
            'rate' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function attributes()
    {
        return [
            'starts' => 'время начала',
            'ends' => 'время окончания',
            'rate' => 'рейт %',
        ];
    }
}
