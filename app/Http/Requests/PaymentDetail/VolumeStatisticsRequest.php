<?php

declare(strict_types=1);

namespace App\Http\Requests\PaymentDetail;

use App\Services\PaymentDetail\PaymentDetailVolumeStatisticsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VolumeStatisticsRequest extends FormRequest
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
                Rule::in(array_column(PaymentDetailVolumeStatisticsService::PERIOD_OPTIONS, 'value')),
            ],
        ];
    }

    public function period(): string
    {
        return $this->string('period')->toString() ?: 'current_month';
    }
}
