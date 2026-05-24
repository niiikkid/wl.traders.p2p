<?php

namespace App\Http\Requests\PaymentDetail;

use App\Rules\OwnedPaymentDetailSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $fields = $this->input('fields', []);
        $fields = is_array($fields) ? $fields : [];

        if (
            ! isRouteFor('Trader')
            && (in_array('schedule_apply', $fields, true) || in_array('schedule_remove', $fields, true))
        ) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        $fields = $this->input('fields', []);
        $fields = is_array($fields) ? $fields : [];

        $fieldNames = [
            'is_active',
            'daily_limit',
            'monthly_limit',
            'monthly_limit_reset_day',
            'monthly_successful_orders_limit',
            'daily_successful_orders_limit',
            'max_pending_orders_quantity',
            'min_order_amount',
            'max_order_amount',
            'order_interval_minutes',
            'schedule_apply',
            'schedule_remove',
        ];

        return [
            'scope' => ['required', Rule::in(['all', 'tag', 'without_tags', 'selected'])],
            'tag_id' => [
                Rule::requiredIf($this->input('scope') === 'tag'),
                'nullable',
                Rule::exists('payment_detail_tags', 'id')
                    ->where('user_id', $this->user()?->id),
            ],
            'selected_ids' => [
                Rule::requiredIf($this->input('scope') === 'selected'),
                'array',
                'min:1',
            ],
            'selected_ids.*' => [
                'integer',
                Rule::exists('payment_details', 'id')
                    ->where('user_id', $this->user()?->id),
            ],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['required', Rule::in($fieldNames)],

            'is_active' => [
                Rule::requiredIf(in_array('is_active', $fields, true)),
                'boolean',
            ],
            'daily_limit' => [
                Rule::requiredIf(in_array('daily_limit', $fields, true)),
                'numeric',
                'min:0',
            ],
            'monthly_limit' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'monthly_limit_reset_day' => [
                'nullable',
                'integer',
                'min:1',
                'max:31',
                Rule::requiredIf(
                    (in_array('monthly_limit', $fields, true) && $this->input('monthly_limit') !== null && $this->input('monthly_limit') !== '')
                    || (in_array('monthly_successful_orders_limit', $fields, true) && $this->input('monthly_successful_orders_limit') !== null && $this->input('monthly_successful_orders_limit') !== '')
                ),
            ],
            'monthly_successful_orders_limit' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'daily_successful_orders_limit' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'max_pending_orders_quantity' => [
                Rule::requiredIf(in_array('max_pending_orders_quantity', $fields, true)),
                'integer',
                'min:1',
                'max:100000000',
            ],
            'min_order_amount' => ['nullable', 'integer', 'min:0'],
            'max_order_amount' => ['nullable', 'integer', 'min:0', 'gte:min_order_amount'],
            'order_interval_minutes' => ['nullable', 'integer', 'min:1'],
            'payment_detail_schedule_id' => [
                Rule::requiredIf(in_array('schedule_apply', $fields, true)),
                'nullable',
                'integer',
                new OwnedPaymentDetailSchedule($this->user()?->id),
            ],
        ];
    }

    public function attributes()
    {
        return [
            'scope' => __('набор реквизитов'),
            'tag_id' => __('тег'),
            'selected_ids' => __('выбранные реквизиты'),
            'fields' => __('поля'),
            'is_active' => __('активность'),
            'daily_limit' => __('дневной лимит'),
            'monthly_limit' => __('месячный лимит'),
            'monthly_limit_reset_day' => __('день сброса месячного лимита'),
            'monthly_successful_orders_limit' => __('месячный лимит по количеству сделок'),
            'daily_successful_orders_limit' => __('дневной лимит по количеству сделок'),
            'min_order_amount' => __('минимальная сумма сделки'),
            'max_order_amount' => __('максимальная сумма сделки'),
            'order_interval_minutes' => __('интервал между сделками'),
            'payment_detail_schedule_id' => __('рабочее расписание'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullableFields = [
            'monthly_limit',
            'monthly_limit_reset_day',
            'monthly_successful_orders_limit',
            'daily_successful_orders_limit',
            'min_order_amount',
            'max_order_amount',
            'order_interval_minutes',
        ];

        $payload = [];
        foreach ($nullableFields as $field) {
            $value = $this->input($field);
            if ($value === '' || $value === null) {
                $payload[$field] = null;
            }
        }

        if (! empty($payload)) {
            $this->merge($payload);
        }
    }
}
