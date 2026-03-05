<?php

namespace App\Http\Requests;

use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filters.event' => ['nullable', 'string', Rule::in(NotificationEvent::values())],
            'filters.delivery_status' => ['nullable', 'string', Rule::in(NotificationDeliveryStatus::values())],
            'filters.only_unread' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $filters = $this->input('filters', []);

        if (isset($filters['event']) && is_string($filters['event'])) {
            $filters['event'] = strtolower(trim($filters['event'])) ?: null;
        }

        if (isset($filters['delivery_status']) && is_string($filters['delivery_status'])) {
            $filters['delivery_status'] = strtolower(trim($filters['delivery_status'])) ?: null;
        }

        if (isset($filters['only_unread'])) {
            $filters['only_unread'] = filter_var($filters['only_unread'], FILTER_VALIDATE_BOOLEAN);
        }

        $this->merge(['filters' => $filters]);
    }

    public function filters(): array
    {
        $filters = $this->validated('filters', []);

        return [
            'event' => $filters['event'] ?? null,
            'delivery_status' => $filters['delivery_status'] ?? null,
            'only_unread' => (bool)($filters['only_unread'] ?? false),
        ];
    }
}
