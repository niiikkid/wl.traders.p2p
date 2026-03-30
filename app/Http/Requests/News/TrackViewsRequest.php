<?php

namespace App\Http\Requests\News;

use Illuminate\Foundation\Http\FormRequest;

class TrackViewsRequest extends FormRequest
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
            'post_ids' => ['required', 'array', 'min:1', 'max:50'],
            'post_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ];
    }
}
