<?php

declare(strict_types=1);

namespace App\Http\Requests\PaymentDetailSchedule;

use App\Rules\PaymentDetailScheduleIntervals;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('payment_detail_schedules', 'name')->where('user_id', $this->user()?->id),
            ],
            'intervals' => ['required', 'array', 'min:1', new PaymentDetailScheduleIntervals],
            'intervals.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'intervals.*.starts_at' => ['required', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'intervals.*.ends_at' => ['required', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'название',
            'intervals' => 'интервалы',
            'intervals.*.day_of_week' => 'день недели',
            'intervals.*.starts_at' => 'время начала',
            'intervals.*.ends_at' => 'время окончания',
        ];
    }
}
