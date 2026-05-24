<?php

declare(strict_types=1);

namespace App\Http\Requests\PaymentDetail;

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
        $rules = [
            'period' => ['nullable', 'string', Rule::in(['1d', '7d', '14d', '30d', 'all'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'bars_limit' => ['nullable', 'string', Rule::in(['25', '50', '75', '100', '200'])],
            'include_archived' => ['nullable', 'boolean'],
            'payment_gateway_id' => ['nullable', 'integer', 'exists:payment_gateways,id'],
        ];

        if ($this->routeIs('admin.payment-details.volume-statistics')) {
            $rules['trader_id'] = ['nullable', 'integer', 'exists:users,id'];
        }

        return $rules;
    }

    public function paymentGatewayId(): ?int
    {
        $paymentGatewayId = $this->integer('payment_gateway_id');

        return $paymentGatewayId > 0 ? $paymentGatewayId : null;
    }

    public function includeArchived(): bool
    {
        return $this->boolean('include_archived');
    }

    public function barsLimit(): ?string
    {
        $value = trim($this->string('bars_limit')->toString());

        return $value !== '' ? $value : null;
    }

    public function period(): string
    {
        return $this->string('period')->toString() ?: 'all';
    }

    public function traderId(): ?int
    {
        if (! $this->routeIs('admin.payment-details.volume-statistics')) {
            return null;
        }

        $traderId = $this->integer('trader_id');

        return $traderId > 0 ? $traderId : null;
    }

    public function dateFrom(): ?string
    {
        $value = $this->string('date_from')->toString();

        return $value !== '' ? $value : null;
    }

    public function dateTo(): ?string
    {
        $value = $this->string('date_to')->toString();

        return $value !== '' ? $value : null;
    }
}
