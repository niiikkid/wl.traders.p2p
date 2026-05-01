<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CascadeMerchantLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableCascadeMerchantLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var CascadeMerchantLog $this
         */
        return [
            'id' => $this->id,
            'type' => $this->direction,
            'payment_type' => $this->payment_type,
            'payment_type_label' => CascadeMerchantLog::paymentTypeLabel($this->payment_type),
            'operation' => $this->operation,
            'operation_label' => CascadeMerchantLog::operationLabel($this->operation),
            'direction' => $this->direction,
            'direction_label' => $this->direction === 'outgoing' ? 'Callback' : 'API',
            'method' => $this->method,
            'url' => $this->url,
            'status_code' => $this->status_code,
            'execution_time' => $this->execution_time,
            'is_successful' => $this->is_successful,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'request_payload' => $this->request_payload,
            'response_payload' => $this->response_payload,
            'merchant' => $this->merchant ? [
                'id' => $this->merchant->id,
                'name' => $this->merchant->name,
                'uuid' => $this->merchant->uuid,
            ] : null,
            'cascade_deal' => $this->cascadeDeal ? [
                'id' => $this->cascadeDeal->id,
                'uuid' => $this->cascadeDeal->uuid,
                'external_id' => $this->cascadeDeal->external_id,
            ] : null,
            'payout' => $this->payout ? [
                'id' => $this->payout->id,
                'uuid' => $this->payout->uuid,
                'external_id' => $this->payout->external_id,
            ] : null,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
