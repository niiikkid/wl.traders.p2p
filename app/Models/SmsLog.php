<?php

namespace App\Models;

use App\Enums\SmsType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $sender
 * @property string $message
 * @property array $parsing_result
 * @property string $timestamp
 * @property SmsType $type
 * @property int $user_id
 * @property int $user_device_id
 * @property int|null $order_id
 * @property Carbon|null $rejected_at
 * @property User $user
 * @property UserDevice $device
 * @property Order $order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SmsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender',
        'message',
        'parsing_result',
        'timestamp',
        'type',
        'user_id',
        'user_device_id',
        'order_id',
        'rejected_at',
    ];

    protected $casts = [
        'type' => SmsType::class,
        'parsing_result' => 'array',
        'rejected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function device()
    {
        return $this->belongsTo(UserDevice::class, 'user_device_id');
    }

    public function scopeWhereOperationTypeUndefined(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('parsing_result')
                ->orWhereRaw("JSON_EXTRACT(parsing_result, '$.operation_type') IS NULL")
                ->orWhereRaw(
                    "LOWER(JSON_UNQUOTE(JSON_EXTRACT(parsing_result, '$.operation_type'))) NOT IN ('in', 'out')"
                );
        });
    }

    public function scopeIncomingPayments(Builder $query): Builder
    {
        return $query->whereRaw(
            "LOWER(JSON_UNQUOTE(JSON_EXTRACT(parsing_result, '$.operation_type'))) = 'in'"
        );
    }

    public function scopeUnlinked(Builder $query): Builder
    {
        return $query->whereNull('order_id');
    }

    public function scopeNotRejected(Builder $query): Builder
    {
        return $query->whereNull('rejected_at');
    }

    public function scopeAwaitingLink(Builder $query): Builder
    {
        return $query->unlinked()->notRejected();
    }

    public function isRejected(): bool
    {
        return $this->rejected_at !== null;
    }

    /**
     * @param  array<int, string>  $operationTypes
     */
    public function scopeWhereSmsOperationTypes(Builder $query, array $operationTypes): Builder
    {
        if ($operationTypes === []) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($operationTypes): void {
            foreach ($operationTypes as $operationType) {
                if ($operationType === 'in') {
                    $query->orWhere(fn (Builder $query) => $query->incomingPayments());

                    continue;
                }

                if ($operationType === 'out') {
                    $query->orWhereRaw(
                        "LOWER(JSON_UNQUOTE(JSON_EXTRACT(parsing_result, '$.operation_type'))) = 'out'"
                    );

                    continue;
                }

                $query->orWhere(fn (Builder $query) => $query->whereOperationTypeUndefined());
            }
        });
    }

    public function scopeLinkableToOrder(Builder $query): Builder
    {
        return $query->notRejected()->where(function (Builder $query): void {
            $query->whereNull('parsing_result')
                ->orWhereRaw("JSON_EXTRACT(parsing_result, '$.operation_type') IS NULL")
                ->orWhereRaw(
                    "LOWER(JSON_UNQUOTE(JSON_EXTRACT(parsing_result, '$.operation_type'))) <> 'out'"
                );
        });
    }

    public function scopeWhereOnlyUnlinkedIncoming(Builder $query, bool $enabled): Builder
    {
        if (! $enabled) {
            return $query;
        }

        return $query->incomingPayments()->awaitingLink();
    }
}
