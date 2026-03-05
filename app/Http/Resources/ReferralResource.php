<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var User $this
         */
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_uuid' => $this->avatar_uuid,
            'avatar_style' => $this->avatar_style,
            'team_leader_id' => $this->team_leader_id,
            'orders_count' => $this->when(isset($this->orders_count), $this->orders_count),
            'total_profit' => $this->when(isset($this->total_team_leader_profit), fn() => $this->total_team_leader_profit->toBeauty()),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
