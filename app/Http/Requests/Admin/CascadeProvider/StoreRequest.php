<?php

namespace App\Http\Requests\Admin\CascadeProvider;

use App\Enums\ProviderType;
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
            'code' => ['required', 'string', Rule::in($this->implementedCodes())],
            'name' => ['required', 'string', 'max:255'],
            'provider_type' => ['required', Rule::in(ProviderType::values())],
            'is_active' => ['required', 'boolean'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'access_token' => ['nullable', 'string', 'max:255'],
            'merchant_id' => ['nullable', 'string', 'max:255'],
            'target_merchant_id' => ['required_unless:provider_type,internal', 'nullable', 'integer', 'exists:merchants,id'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'timeout' => ['nullable', 'integer', 'min:1', 'max:300'],
            'verify_ssl' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => __('код провайдера'),
            'name' => __('название'),
            'provider_type' => __('тип провайдера'),
            'is_active' => __('активность'),
            'weight' => __('вес'),
            'priority' => __('приоритет'),
            'base_url' => __('base URL'),
            'access_token' => __('access token'),
            'merchant_id' => __('merchant ID'),
            'target_merchant_id' => __('мерчант'),
            'currency_code' => __('валюта'),
            'timeout' => __('таймаут'),
            'verify_ssl' => __('проверка SSL'),
            'description' => __('описание'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'verify_ssl' => $this->boolean('verify_ssl'),
            'currency_code' => $this->currency_code ? strtoupper((string) $this->currency_code) : null,
        ]);
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
