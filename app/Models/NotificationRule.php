<?php

namespace App\Models;

use App\Casts\CurrencyCast;
use App\Enums\NotificationEvent;
use App\Enums\NotificationMessageScope;
use App\Services\Money\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property NotificationEvent $event
 * @property Currency|null $currency
 * @property array|null $statuses
 * @property NotificationMessageScope|null $message_scope
 * @property string|null $min_amount_minor
 * @property bool $enabled
 */
class NotificationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event',
        'currency',
        'statuses',
        'message_scope',
        'min_amount_minor',
        'enabled',
    ];

    protected $casts = [
        'event' => NotificationEvent::class,
        'currency' => CurrencyCast::class,
        'statuses' => 'array',
        'message_scope' => NotificationMessageScope::class,
        'enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
