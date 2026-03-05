<?php

namespace App\Http\Requests\Admin\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Используем поле login, но сохраняем в колонку email
            'login' => 'required|string|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'team_leader_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function attributes()
    {
        return [
            'role_id' => __('роль'),
            'team_leader_id' => __('тим лидер'),
        ];
    }
}
