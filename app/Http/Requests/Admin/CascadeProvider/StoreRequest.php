<?php

namespace App\Http\Requests\Admin\CascadeProvider;

use App\Enums\ProviderType;
use App\Models\CascadeProvider;
use App\Models\User;
use App\Services\Cascade\CascadeProviderDiscoveryService;
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
            'code' => ['required', 'string', Rule::in($this->implementedCodes()), Rule::unique(CascadeProvider::class)],
            'name' => ['required', 'string', 'max:255'],
            'provider_type' => ['required', Rule::in(ProviderType::values())],
            'user_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'is_active' => ['required', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'min_profit_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'access_token' => ['nullable', 'string', 'max:255'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'timeout' => ['required', 'integer', 'min:1', 'max:10'],
            'verify_ssl' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => __('код провайдера'),
            'name' => __('название'),
            'provider_type' => __('тип провайдера'),
            'user_id' => __('пользователь провайдера ликвидности'),
            'is_active' => __('активность'),
            'priority' => __('приоритет'),
            'min_profit_percent' => __('минимальная прибыль'),
            'base_url' => __('base URL'),
            'access_token' => __('access token'),
            'currency_code' => __('валюта'),
            'timeout' => __('таймаут'),
            'verify_ssl' => __('проверка SSL'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'verify_ssl' => $this->boolean('verify_ssl'),
            'currency_code' => $this->currency_code ? strtoupper((string) $this->currency_code) : null,
            'timeout' => $this->timeout ?: 10,
            'min_profit_percent' => $this->min_profit_percent ?? 0,
        ]);

        if ($this->input('code') === 'internal') {
            $this->merge([
                'provider_type' => ProviderType::INTERNAL->value,
                'user_id' => null,
                'priority' => null,
                'min_profit_percent' => 0,
                'base_url' => null,
                'access_token' => null,
                'currency_code' => null,
                'verify_ssl' => true,
            ]);
        } else {
            $this->merge([
                'provider_type' => ProviderType::EXTERNAL->value,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function implementedCodes(): array
    {
        return app(CascadeProviderDiscoveryService::class)
            ->implementedProviders()
            ->pluck('code')
            ->all();
    }
}
