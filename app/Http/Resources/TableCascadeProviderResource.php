<?php

namespace App\Http\Resources;

use App\Models\CascadeProvider;
use App\Services\Cascade\CascadeProviderDiscoveryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableCascadeProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var CascadeProvider $this
         */
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'provider_type' => $this->provider_type?->value,
            'provider_type_name' => $this->provider_type?->value === 'internal' ? 'Внутренний' : 'Внешний',
            'is_active' => $this->is_active,
            'weight' => $this->weight,
            'priority' => $this->priority,
            'base_url' => $this->base_url,
            'access_token' => $this->access_token,
            'merchant_id' => $this->merchant_id,
            'target_merchant_id' => $this->target_merchant_id,
            'target_merchant_name' => $this->targetMerchant?->name,
            'target_merchant_uuid' => $this->targetMerchant?->uuid,
            'callback_url' => $this->callback_url,
            'callback_endpoint_url' => url('/api/v2/providers/'.$this->code.'/callback'),
            'supports_callback_endpoint' => $this->supportsCallbackEndpoint(),
            'currency_code' => $this->currency_code,
            'timeout' => $this->timeout,
            'verify_ssl' => $this->verify_ssl,
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function supportsCallbackEndpoint(): bool
    {
        /** @var array<string, bool>|null $map */
        static $map = null;

        if ($map === null) {
            $map = app(CascadeProviderDiscoveryService::class)
                ->implementedProviders()
                ->mapWithKeys(
                    fn (array $providerMeta) => [
                        $providerMeta['code'] => (bool) ($providerMeta['supports_callback_endpoint'] ?? false),
                    ]
                )
                ->all();
        }

        return $map[$this->code] ?? false;
    }
}
