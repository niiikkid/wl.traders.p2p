<?php

namespace App\Http\Requests\News;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
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
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'content_json' => ['required', 'array'],
            'cover_image' => ['nullable', 'file', 'extensions:jpg,jpeg,png,webp', 'max:4096'],
            'visibility_type' => ['required', 'in:all,roles'],
            'visible_roles' => ['required_if:visibility_type,roles', 'array'],
            'visible_roles.*' => ['required', 'string', 'distinct', 'in:Trader,Support,Team Leader,Agent'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'заголовок новости',
            'content_json' => 'контент новости',
            'cover_image' => 'обложка новости',
            'visibility_type' => 'тип видимости',
            'visible_roles' => 'список ролей',
        ];
    }
}
