<?php

namespace App\Http\Requests\PaymentDetail;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fields = $this->input('fields', []);
        $fields = is_array($fields) ? $fields : [];

        $fieldNames = [
            'is_active',
            'daily_limit',
            'daily_successful_orders_limit',
            'max_pending_orders_quantity',
            'min_order_amount',
            'max_order_amount',
            'order_interval_minutes',
        ];

        return [
            'scope' => ['required', Rule::in(['all', 'tag', 'without_tags'])],
            'tag_id' => [
                Rule::requiredIf($this->input('scope') === 'tag'),
                'nullable',
                Rule::exists('payment_detail_tags', 'id')
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
        ];
    }

    public function attributes()
    {
        return [
            'scope' => __('набор реквизитов'),
            'tag_id' => __('тег'),
            'fields' => __('поля'),
            'is_active' => __('активность'),
            'daily_limit' => __('дневной лимит'),
            'daily_successful_orders_limit' => __('дневной лимит по количеству сделок'),
            'min_order_amount' => __('минимальная сумма сделки'),
            'max_order_amount' => __('максимальная сумма сделки'),
            'order_interval_minutes' => __('интервал между сделками'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullableFields = [
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
