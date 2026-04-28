<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProviderType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Настройки провайдера каскада
 *
 * Хранит конфигурацию и настройки провайдеров ликвидности (внутренних и внешних).
 * Используется для управления включением/выключением провайдеров, распределения трафика,
 * приоритизации и хранения конфигурации (API ключи, URL и т.д.).
 *
 * @property int $id
 * @property string $code Уникальный код провайдера (например, 'internal', 'external_provider_1')
 * @property string $name Название провайдера для отображения
 * @property ProviderType $provider_type Тип провайдера (internal/external)
 * @property int|null $user_id ID пользователя Provider Liquidity
 * @property bool $is_active Включен ли провайдер (попадает ли в обработчик)
 * @property int $priority Порядок приоритета (чем меньше число, тем выше приоритет)
 * @property float $min_profit_percent Минимальный процент прибыли для внешнего провайдера
 * @property string|null $base_url Базовый URL провайдера
 * @property string|null $access_token Токен доступа к API
 * @property string|null $callback_url Callback URL для провайдера
 * @property string|null $currency_code Валюта для запросов к провайдеру
 * @property int|null $timeout Таймаут запросов (сек)
 * @property bool $verify_ssl Проверка SSL-сертификата
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CascadeProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'provider_type',
        'user_id',
        'is_active',
        'priority',
        'min_profit_percent',
        'base_url',
        'access_token',
        'callback_url',
        'currency_code',
        'timeout',
        'verify_ssl',
    ];

    protected $casts = [
        'provider_type' => ProviderType::class,
        'is_active' => 'boolean',
        'priority' => 'integer',
        'min_profit_percent' => 'float',
        'timeout' => 'integer',
        'verify_ssl' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CascadeDeal::class, 'selected_provider_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CascadeTransaction::class, 'provider_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CascadeProviderLog::class, 'provider_id');
    }
}
