<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\WithdrawalAddressResource;
use App\Models\WithdrawalAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalAddressUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'addresses' => $this->withdrawalAddresses->map(fn (WithdrawalAddress $address): array => [
                ...(new WithdrawalAddressResource($address))->resolve($request),
                'invoices_count' => (int) $address->invoices_count,
            ])->all(),
        ];
    }
}
