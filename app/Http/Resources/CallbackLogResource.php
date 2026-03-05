<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\Payout\Payout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallbackLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $callbackable = null;
        
        if ($this->callbackable instanceof Order) {
            $callbackable = [
                'id' => $this->callbackable->id,
                'uuid' => $this->callbackable->uuid,
                'type' => 'order',
            ];
        } elseif ($this->callbackable instanceof Payout) {
            $callbackable = [
                'id' => $this->callbackable->id,
                'uuid' => $this->callbackable->uuid,
                'type' => 'payout',
            ];
        }
        
        return [
            'id' => $this->id,
            'type' => $this->type,
            'callbackable' => $callbackable,
            'url' => $this->url,
            'request_data' => $this->request_data,
            'response_data' => $this->response_data,
            'status_code' => $this->status_code,
            'is_success' => $this->is_success,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
