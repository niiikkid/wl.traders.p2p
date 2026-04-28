<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantCascadeSetting extends Model
{
    protected $fillable = [
        'merchant_id',
        'cascade_enabled',
        'allow_internal_providers',
        'allow_external_providers',
        'allowed_provider_ids',
    ];

    protected $casts = [
        'cascade_enabled' => 'boolean',
        'allow_internal_providers' => 'boolean',
        'allow_external_providers' => 'boolean',
        'allowed_provider_ids' => 'array',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
