<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CascadeDealEventType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cascade_deal_id
 * @property CascadeDealEventType $type
 * @property array|null $payload
 * @property Carbon $created_at
 */
class CascadeDealEvent extends Model
{
    protected $fillable = [
        'cascade_deal_id',
        'cascade_transaction_id',
        'provider_id',
        'user_id',
        'type',
        'from_status',
        'from_sub_status',
        'to_status',
        'to_sub_status',
        'payload',
    ];

    protected $casts = [
        'type' => CascadeDealEventType::class,
        'payload' => 'array',
    ];

    public function cascadeDeal(): BelongsTo
    {
        return $this->belongsTo(CascadeDeal::class);
    }

    public function cascadeTransaction(): BelongsTo
    {
        return $this->belongsTo(CascadeTransaction::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(CascadeProvider::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
