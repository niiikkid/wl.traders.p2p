<?php

namespace App\Http\Requests\Admin\WithdrawalAddress;

use App\Http\Requests\WithdrawalAddress\StoreWithdrawalAddressRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class DestroyRequest extends StoreWithdrawalAddressRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Super Admin') === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'one_time_password' => parent::rules()['one_time_password'],
        ];
    }
}
