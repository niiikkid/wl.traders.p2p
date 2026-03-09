<?php

namespace App\Http\Requests;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $event = $this->has('event') && is_string($this->event)
            ? NotificationEvent::tryFrom(strtolower(trim($this->event)))
            : null;

        $baseRules = [
            'event' => ['nullable', 'string', Rule::in(NotificationEvent::values())],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', Rule::in(NotificationChannel::values())],
            'currency' => ['nullable', 'string', Rule::in(Currency::getAllCodes())],
            'min_amount' => ['nullable', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string'],
            'enabled' => ['nullable', 'boolean'],
        ];

        if ($this->isMethod('post')) {
            $baseRules['event'][0] = 'required';
            $baseRules['channels'] = ['required', 'array', 'min:1'];
        }

        if ($event?->equals(NotificationEvent::TRUST_BALANCE_LOW)) {
            $baseRules['min_amount'] = ['required', 'string', 'regex:/^\d+(\.\d+)?$/'];
        }

        if ($this->filled('min_amount') && ! $event?->equals(NotificationEvent::TRUST_BALANCE_LOW)) {
            $baseRules['currency'][] = 'required';
        }

        return $baseRules;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('event') && is_string($this->event)) {
            $this->merge(['event' => strtolower(trim($this->event)) ?: null]);
        }

        if ($this->has('currency') && is_string($this->currency)) {
            $currency = trim($this->currency);
            $this->merge(['currency' => $currency !== '' ? strtolower($currency) : null]);
        }

        if ($this->has('min_amount') && is_string($this->min_amount)) {
            $amount = trim($this->min_amount);
            $this->merge(['min_amount' => $amount !== '' ? $amount : null]);
        }
    }

    public function minAmountMinor(): ?string
    {
        $eventValue = $this->validated('event');
        $event = $eventValue ? NotificationEvent::from($eventValue) : null;
        $minAmount = $this->validated('min_amount');
        $currency = $event?->equals(NotificationEvent::TRUST_BALANCE_LOW)
            ? Currency::USDT()->getCode()
            : $this->validated('currency');

        if (! $minAmount || ! $currency) {
            return null;
        }

        return Money::fromPrecision($minAmount, $currency)->toUnits();
    }
}
