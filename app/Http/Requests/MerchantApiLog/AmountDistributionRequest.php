<?php

declare(strict_types=1);

namespace App\Http\Requests\MerchantApiLog;

use App\Services\Money\Currency;
use App\Services\Statistics\AmountDistributionBucketService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AmountDistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => [
                'nullable',
                'string',
                Rule::in(array_column(AmountDistributionBucketService::PERIOD_OPTIONS, 'value')),
            ],
            'currency' => [
                'nullable',
                'string',
                Rule::in(Currency::getAllCodes()),
            ],
        ];
    }

    public function period(): string
    {
        return $this->string('period')->toString() ?: 'current_month';
    }

    public function currency(): string
    {
        $currency = strtolower($this->string('currency')->toString());

        return $currency !== '' ? $currency : 'uah';
    }
}
