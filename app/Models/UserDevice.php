<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $token
 * @property string|null $android_id
 * @property string|null $device_model
 * @property string|null $android_version
 * @property string|null $manufacturer
 * @property string|null $brand
 * @property Carbon|null $connected_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $user
 */
class UserDevice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'token',
        'android_id',
        'device_model',
        'android_version',
        'manufacturer',
        'brand',
        'connected_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'connected_at' => 'datetime',
    ];

    /**
     * Генерирует уникальный токен для устройства
     *
     * @return string
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Получить пользователя, которому принадлежит устройство
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
