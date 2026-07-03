<?php

namespace App\Http\Resources;

use App\Models\SmsLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmsLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var SmsLog $this
         */

        return [
            'id' => $this->id,
            'device' => $this->whenLoaded('device', function () {
                return [
                    'id' => $this->device->id,
                    'name' => $this->device->name,
                    'android_id' => $this->device->android_id,
                ];
            }),
            'order' => $this->whenLoaded('order', function () {
                if ($this->order === null) {
                    return null;
                }

                $order = $this->order;

                return [
                    'id' => $order->id,
                    'uuid' => $order->uuid,
                    'amount' => $order->amount->toBeauty(),
                    'currency' => $order->currency->getCode(),
                    'status' => $order->status->value,
                    'status_name' => $order->status_name,
                    'created_at' => $order->created_at->toISOString(),
                    'payment_gateway_name' => $order->paymentGateway?->name,
                    'payment_gateway_logo_path' => $order->paymentGateway?->logoUrl(),
                    'payment_detail' => $order->paymentDetail?->detail,
                    'payment_detail_type' => $order->paymentDetail?->detail_type?->value,
                    'payment_detail_name' => $order->paymentDetail?->name,
                ];
            }),
            'sender' => $this->sender,
            'message' => $this->message,
            'sender_exists' => false,
            'payment_gateway' => null,
            'parsing_result' => $this->parsing_result,
            'timestamp' => Carbon::createFromTimestamp($this->timestamp)->toDateTimeString(),
            'type' => $this->type->value,
            'created_at' => $this->created_at->toDateTimeString(),
            'rejected_at' => $this->rejected_at?->toDateTimeString(),
            'is_rejected' => $this->rejected_at !== null,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'email' => $this->user->email,
                ];
            }),
        ];
    }
}
