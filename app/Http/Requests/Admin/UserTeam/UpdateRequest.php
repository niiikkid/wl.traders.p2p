<?php

namespace App\Http\Requests\Admin\UserTeam;

use App\Models\UserTeam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /**
         * @var UserTeam $userTeam
         */
        $userTeam = $this->route('userTeam');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('user_teams', 'name')->ignore($userTeam->id),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('название команды'),
        ];
    }
}
