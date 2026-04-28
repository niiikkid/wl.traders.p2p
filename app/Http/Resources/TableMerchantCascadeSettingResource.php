<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Merchant;
use App\Models\MerchantCascadeSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableMerchantCascadeSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Merchant $this */
        $setting = $this->cascadeSetting;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'domain' => $this->domain,
            'active' => $this->active,
            'owner' => [
                'id' => $this->user?->id,
                'email' => $this->user?->email,
            ],
            'cascade_setting' => $this->settingPayload($setting),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingPayload(?MerchantCascadeSetting $setting): array
    {
        return [
            'id' => $setting?->id,
            'cascade_enabled' => $setting?->cascade_enabled ?? true,
            'allow_internal_providers' => $setting?->allow_internal_providers ?? true,
            'allow_external_providers' => $setting?->allow_external_providers ?? true,
            'allowed_provider_ids' => collect($setting?->allowed_provider_ids ?? [])
                ->map(fn (mixed $id): int => (int) $id)
                ->filter()
                ->values()
                ->all(),
            'is_default' => $setting === null,
        ];
    }
}
