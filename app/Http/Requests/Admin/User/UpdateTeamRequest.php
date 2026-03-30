<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_team_id' => ['nullable', 'integer', 'exists:user_teams,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_team_id' => __('команда'),
        ];
    }
}
