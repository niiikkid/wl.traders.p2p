<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $merchant_id
 * @property bool $enabled
 * @property int|null $primary_max_pending
 * @property array|null $primary_rate_limits
 * @property int|null $primary_failed_limit
 * @property int|null $primary_block_days
 * @property bool $secondary_enabled
 * @property int|null $secondary_max_pending
 * @property array|null $secondary_rate_limits
 * @property int|null $secondary_failed_limit
 * @property int|null $secondary_block_days
 * @property Merchant $merchant
 */
class AntiFraudSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'enabled',
        'primary_max_pending',
        'primary_rate_limits',
        'primary_failed_limit',
        'primary_block_days',
        'secondary_enabled',
        'secondary_max_pending',
        'secondary_rate_limits',
        'secondary_failed_limit',
        'secondary_block_days',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'primary_rate_limits' => 'array',
        'secondary_enabled' => 'boolean',
        'secondary_rate_limits' => 'array',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
