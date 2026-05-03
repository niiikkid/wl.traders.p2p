<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

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
 * @property int $provider_id ID провайдера
 * @property string $operation Код операции (createDeal, cancelDeal, callback и т.д., см. {@see self::operationLabel})
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
 * @property CascadeDeal|null $cascadeDeal
 * @property CascadeTransaction|null $cascadeTransaction
 * @property CascadeProvider $provider
 */
class CascadeProviderLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'cascade_deal_id',
        'cascade_transaction_id',
        'provider_id',
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

    public function provider(): BelongsTo
    {
        return $this->belongsTo(CascadeProvider::class);
    }

    /**
     * Ограничить выборку логами конкретной интеграции (tenant-провайдер).
     */
    public function scopeForCascadeProvider(Builder $query, CascadeProvider $provider): Builder
    {
        return $query->where('provider_id', $provider->getKey());
    }

    /**
     * @param  Collection<int, CascadeProvider>  $providers
     */
    public function scopeForCascadeProviders(Builder $query, Collection $providers): Builder
    {
        return $query->whereIn('provider_id', $providers->pluck('id')->all());
    }

    /**
     * Человекочитаемая подпись кода операции для UI (логи, фильтры).
     */
    public static function operationLabel(string $operation): string
    {
        return match ($operation) {
            'createDeal' => 'Создание сделки',
            'cancelDeal' => 'Отмена сделки',
            'getDeal' => 'Получение сделки',
            'openDispute' => 'Открытие спора',
            'getDispute' => 'Данные спора',
            'cancelDispute' => 'Отмена спора',
            'storeConfirmationCode' => 'Код подтверждения',
            'callback' => 'Входящий callback',
            default => $operation,
        };
    }

    /**
     * Тело ответа провайдера для колонки лога: буквальный JSON из HTTP, если адаптер кладёт его в {@see $adapterPayload}['raw'].
     *
     * @param  array<string, mixed>|null  $adapterPayload  Возврат метода адаптера (нормализованные поля + опционально raw).
     * @return array<string, mixed>|null
     */
    public static function literalHttpJsonForLog(?array $adapterPayload): ?array
    {
        if ($adapterPayload === null) {
            return null;
        }

        $raw = $adapterPayload['raw'] ?? null;
        $meta = array_filter(
            Arr::only($adapterPayload, ['status_code', 'request_id', 'duration', 'error_code', 'error_message']),
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );

        if (! is_array($raw)) {
            return $adapterPayload;
        }

        if ($meta !== [] && ! array_key_exists('_cascade_meta', $raw)) {
            $raw['_cascade_meta'] = $meta;
        }

        return $raw;
    }
}
