<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CascadeProviderLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableCascadeProviderLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var CascadeProviderLog $this
         */
        return [
            'id' => $this->id,
            'type' => $this->operation === 'callback' ? 'callback' : 'api',
            'operation' => $this->operation,
            'method' => $this->method,
            'url' => $this->url,
            'status_code' => $this->status_code,
            'execution_time' => $this->execution_time,
            'is_successful' => $this->is_successful,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'request_payload' => $this->request_payload,
            'response_payload' => $this->response_payload,
            'provider' => $this->provider ? [
                'id' => $this->provider->id,
                'code' => $this->provider->code,
                'name' => $this->provider->name,
            ] : null,
            'cascade_deal' => $this->cascadeDeal ? [
                'id' => $this->cascadeDeal->id,
                'uuid' => $this->cascadeDeal->uuid,
                'external_id' => $this->cascadeDeal->external_id,
                'merchant' => [
                    'id' => $this->cascadeDeal->merchant?->id,
                    'name' => $this->cascadeDeal->merchant?->name,
                    'uuid' => $this->cascadeDeal->merchant?->uuid,
                ],
            ] : null,
            'cascade_transaction' => $this->cascadeTransaction ? [
                'id' => $this->cascadeTransaction->id,
                'status' => $this->cascadeTransaction->status?->value,
                'provider_deal_id' => $this->cascadeTransaction->provider_deal_id,
            ] : null,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
