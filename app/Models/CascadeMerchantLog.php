<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Payout\Payout;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CascadeMerchantLog extends Model
{
    use HasFactory;

    public const PAYMENT_TYPE_PAYIN = 'payin';

    public const PAYMENT_TYPE_PAYOUT = 'payout';

    protected $fillable = [
        'cascade_deal_id',
        'payout_id',
        'merchant_id',
        'payment_type',
        'operation',
        'direction',
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

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public static function operationLabel(string $operation): string
    {
        return match ($operation) {
            'createDeal' => 'Создание сделки',
            'cancelDeal' => 'Отмена сделки',
            'storeConfirmationCode' => 'Код подтверждения',
            'openDispute' => 'Открытие спора',
            'createPayout' => 'Создание выплаты',
            'cancelPayout' => 'Отмена выплаты',
            'callback' => 'Callback',
            default => $operation,
        };
    }

    public static function paymentTypeLabel(?string $paymentType): string
    {
        return match ($paymentType) {
            self::PAYMENT_TYPE_PAYOUT => 'Payout',
            default => 'Pay-in',
        };
    }
}
