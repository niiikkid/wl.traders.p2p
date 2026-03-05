<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProviderType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Лог запросов к провайдерам каскада
 *
 * Хранит историю всех запросов к провайдерам (внутренним и внешним):
 * создание сделки, отмена, получение статуса, открытие спора и т.д.
 * Позволяет проследить полную историю взаимодействия с каждым провайдером.
 *
 * @property int $id
 * @property int|null $cascade_deal_id ID каскадной сделки (если запрос связан со сделкой)
 * @property int|null $cascade_transaction_id ID транзакции каскада (если запрос связан с транзакцией)
 * @property string $provider_code Код провайдера (например, 'internal', 'external_provider_1')
 * @property ProviderType $provider_type Тип провайдера (internal/external)
 * @property string $operation Тип операции (createDeal, cancelDeal, getDeal, openDispute, getDispute)
 * @property string $method HTTP метод (GET, POST, PUT, DELETE)
 * @property string $url URL/endpoint запроса к провайдеру
 * @property array|null $request_payload Тело запроса (JSON)
 * @property array|null $response_payload Тело ответа (JSON)
 * @property int|null $status_code HTTP статус код ответа
 * @property float|null $execution_time Время выполнения запроса в секундах
 * @property bool $is_successful Успешен ли запрос
 * @property string|null $error_code Код ошибки (если запрос неуспешен)
 * @property string|null $error_message Сообщение об ошибке (если запрос неуспешен)
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property CascadeDeal|null $cascadeDeal
 * @property CascadeTransaction|null $cascadeTransaction
 */
class CascadeProviderLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'cascade_deal_id',
        'cascade_transaction_id',
        'provider_code',
        'provider_type',
        'operation',
        'method',
        'url',
        'request_payload',
        'response_payload',
        'status_code',
        'execution_time',
        'is_successful',
        'error_code',
        'error_message',
    ];

    protected $casts = [
        'provider_type' => ProviderType::class,
        'request_payload' => 'array',
        'response_payload' => 'array',
        'status_code' => 'integer',
        'execution_time' => 'float',
        'is_successful' => 'boolean',
    ];

    public function cascadeDeal(): BelongsTo
    {
        return $this->belongsTo(CascadeDeal::class);
    }

    public function cascadeTransaction(): BelongsTo
    {
        return $this->belongsTo(CascadeTransaction::class);
    }
}
