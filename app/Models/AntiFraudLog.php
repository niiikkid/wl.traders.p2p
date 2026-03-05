<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $merchant_id
 * @property int|null $merchant_client_id
 * @property string|null $client_id
 * @property string $decision
 * @property string|null $message
 * @property array|null $meta
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Merchant $merchant
 * @property MerchantClient|null $merchantClient
 */
class AntiFraudLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'merchant_client_id',
        'client_id',
        'decision',
        'message',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function merchantClient(): BelongsTo
    {
        return $this->belongsTo(MerchantClient::class);
    }
}
