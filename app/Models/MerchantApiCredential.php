<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $merchant_id
 * @property string $api_token
 * @property string $callback_token
 * @property Merchant $merchant
 */
class MerchantApiCredential extends Model
{
    protected $fillable = [
        'merchant_id',
        'api_token',
        'callback_token',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function regenerateApiToken(): string
    {
        $this->forceFill([
            'api_token' => self::generateUniqueToken('api_token'),
        ])->save();

        return $this->api_token;
    }

    public function regenerateCallbackToken(): string
    {
        $this->forceFill([
            'callback_token' => self::generateUniqueToken('callback_token'),
        ])->save();

        return $this->callback_token;
    }

    public static function generateUniqueToken(string $column): string
    {
        do {
            $token = strtolower(Str::random(40));
        } while (self::query()->where($column, $token)->exists());

        return $token;
    }
}
