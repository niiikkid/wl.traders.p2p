<?php

namespace App\Http\Requests\Dispute;

use App\Enums\DisputeCancelReasonCode;
use App\Rules\ReceiptFileRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelRequest extends FormRequest
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
        $reasonCode = $this->reasonCode();

        return [
            'reason_code' => ['required', 'string', Rule::in(DisputeCancelReasonCode::values())],
            'reason' => [
                'nullable',
                'string',
                'max:120',
                Rule::requiredIf($reasonCode?->equals(DisputeCancelReasonCode::OTHER)),
            ],
            'bank_statement' => [
                'nullable',
                Rule::requiredIf($reasonCode?->isBankStatementRequired() ?? true),
                'file',
                'max:5120',
                new ReceiptFileRule,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason_code' => 'код причины отклонения',
            'reason' => 'причина отклонения',
            'bank_statement' => 'выписка по карте',
        ];
    }

    public function reasonCode(): ?DisputeCancelReasonCode
    {
        $value = $this->input('reason_code');
        if (! is_string($value)) {
            return null;
        }

        return DisputeCancelReasonCode::tryFrom($value);
    }

    public function validatedReasonCode(): DisputeCancelReasonCode
    {
        return DisputeCancelReasonCode::from($this->validated('reason_code'));
    }
}
