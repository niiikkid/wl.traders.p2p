<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => $this->input('description') === '' ? null : $this->input('description'),
            'project_link' => $this->input('project_link') === '' ? null : $this->input('project_link'),
        ]);
    }

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
            'name' => ['required', 'string', 'min:5', 'max:30'],
            'description' => ['nullable', 'string', 'max:300'],
            'project_link' => ['nullable', 'string', 'url:https', 'max:120'],
        ];
    }

    public function attributes()
    {
        return [
            'project_link' => __('ссылка на проект'),
        ];
    }
}
