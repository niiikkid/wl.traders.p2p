<?php

namespace App\Http\Requests\Trader\Export;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ExportRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $startDate = $this->input('start_date');
            $endDate = $this->input('end_date');

            if (($startDate === null && $endDate === null) || ($startDate !== null && $endDate !== null)) {
                return;
            }

            $validator->errors()->add('start_date', 'Для фильтрации укажите обе даты: "с" и "по".');
        });
    }

    public function dateFrom(): ?Carbon
    {
        $startDate = $this->validated('start_date');

        if (! $startDate) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
    }

    public function dateTo(): ?Carbon
    {
        $endDate = $this->validated('end_date');

        if (! $endDate) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();
    }
}
