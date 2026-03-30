<?php

namespace App\Http\Requests\Admin\UserTeam;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:user_teams,name'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('название команды'),
        ];
    }
}
