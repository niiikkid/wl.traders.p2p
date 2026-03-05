<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CallbackLog extends Model
{
    /**
     * Типы колбеков
     */
    public const TYPE_ORDER = 'order';
    public const TYPE_PAYOUT = 'payout';

    /**
     * Атрибуты, которые можно массово присваивать.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'url',
        'request_data',
        'response_data',
        'status_code',
        'is_success',
        'callbackable_id',
        'callbackable_type',
    ];

    /**
     * Атрибуты, которые должны быть приведены к типам.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'is_success' => 'boolean',
    ];

    /**
     * Получить модель, для которой был отправлен колбек.
     *
     * @return MorphTo
     */
    public function callbackable(): MorphTo
    {
        return $this->morphTo();
    }
}
