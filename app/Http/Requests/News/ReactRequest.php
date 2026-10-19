<?php

namespace App\Http\Requests\News;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return self::canReact($this->user());
    }

    public static function canReact(?User $user): bool
    {
        return $user?->hasAnyRole(['Trader', 'Team Leader', 'Super Admin']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'post_id' => ['required', 'integer', 'min:1'],
            'reaction' => ['required', 'string', 'in:up,down'],
        ];
    }
}
