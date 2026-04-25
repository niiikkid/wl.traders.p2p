<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CascadeTransactionStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Транзакция каскадной сделки у провайдера
 *
 * Хранит каждую попытку создания сделки у конкретного провайдера.
 * Включает как успешные, так и неуспешные попытки, а также отменённые транзакции.
 *
 * @property int $id
 * @property int $cascade_deal_id ID каскадной сделки
 * @property int $provider_id ID провайдера
 * @property CascadeTransactionStatus $status Статус транзакции (opened/failed_to_open/cancelled/accepted)
 * @property string|null $provider_deal_id ID сделки у провайдера (если создана)
 * @property array|null $request_payload Данные запроса к провайдеру (для аудита)
 * @property array|null $response_payload Данные ответа от провайдера (для аудита)
 * @property string|null $error_code Код ошибки (если транзакция неуспешна)
 * @property string|null $error_message Сообщение об ошибке (если транзакция неуспешна)
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property CascadeDeal $cascadeDeal
 * @property CascadeProvider $provider
 */
class CascadeTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'cascade_deal_id',
        'provider_id',
        'status',
        'provider_deal_id',
        'request_payload',
        'response_payload',
        'error_code',
        'error_message',
    ];

    protected $casts = [
        'status' => CascadeTransactionStatus::class,
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function cascadeDeal(): BelongsTo
    {
        return $this->belongsTo(CascadeDeal::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(CascadeProvider::class);
    }

    /**
     * Логи запросов к провайдеру для этой транзакции
     *
     * @return HasMany
     */
    public function providerLogs(): HasMany
    {
        return $this->hasMany(CascadeProviderLog::class);
    }
}
