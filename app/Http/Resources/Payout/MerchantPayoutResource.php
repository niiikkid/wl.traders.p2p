<?php

namespace App\Http\Resources\Payout;

use App\Enums\PayoutMethodType;
use App\Enums\PayoutOperationType;
use App\Enums\PayoutStatus;
use App\Models\Payout\Payout;
use App\Models\Payout\PayoutOperation;
use App\Services\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payout
 */
class MerchantPayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'external_id' => $this->external_id,
            'status' => $this->status->value,
            'status_label' => $this->statusLabel(),
            'payout_method_type' => [
                'value' => $this->payout_method_type->value,
                'label' => $this->methodTypeLabel(),
            ],
            'bank_name' => $this->bank_name,
            'requisites' => $this->requisites,
            'initials' => $this->initials,
            'receipt_url' => $this->receipt_path ? route('payouts.receipts.show', ['payout' => $this->uuid]) : null,
            'amount' => $this->formatMoney($this->amount_fiat),
            'merchant_debit' => $this->formatMoney($this->merchant_debit),
            'usdt_body' => $this->formatMoney($this->usdt_body),
            'fees' => [
                'total' => $this->total_fee?->toBeauty(),
                'currency' => strtoupper($this->total_fee?->getCurrency()->getCode() ?? 'usdt'),
            ],
            'commissions' => [
                'total' => $this->total_commission_rate,
            ],
            'payment_gateway' => [
                'id' => $this->payment_gateway_id,
                'name' => $this->paymentGateway?->name,
                'code' => $this->paymentGateway?->code,
                'logo' => $this->paymentGateway?->logo ? asset('storage/logos/'.$this->paymentGateway?->logo) : null,
                'currency' => $this->paymentGateway?->currency?->getCode()
                    ? strtoupper($this->paymentGateway?->currency?->getCode())
                    : null,
            ],
            'merchant' => [
                'id' => $this->merchant?->id,
                'name' => $this->merchant?->name,
                'owner' => [
                    'id' => $this->merchant?->user?->id,
                    'name' => $this->merchant?->user?->name,
                    'email' => $this->merchant?->user?->email,
                ],
            ],
            'rate' => [
                'market' => $this->rate_market?->value,
                'price' => $this->conversion_price?->toBeauty(),
                'currency' => strtoupper($this->conversion_price?->getCurrency()->getCode() ?? 'usdt'),
                'fixed_at' => $this->rate_fixed_at?->toIso8601String(),
            ],
            'timings' => [
                'created_at' => $this->created_at?->toIso8601String(),
                'completed_at' => $this->completed_at?->toIso8601String(),
            ],
        ];
    }

    private function statusLabel(): string
    {
        return match ($this->status) {
            PayoutStatus::OPEN => 'Открыта',
            PayoutStatus::TAKEN => 'В работе',
            PayoutStatus::SENT => 'Отправлено',
            PayoutStatus::COMPLETED => 'Завершена',
            PayoutStatus::CANCELED => 'Отменена',
        };
    }

    private function methodTypeLabel(): string
    {
        return match ($this->payout_method_type) {
            PayoutMethodType::SBP => 'СБП',
            PayoutMethodType::CARD => 'Карта',
        };
    }

    private function operationTypeLabel(PayoutOperationType $type): string
    {
        return match ($type) {
            PayoutOperationType::RESERVE_FROM_MERCHANT => 'Резерв с мерчанта',
            PayoutOperationType::RETURN_TO_MERCHANT => 'Возврат мерчанту',
            PayoutOperationType::MARK_TAKEN => 'Взятие выплаты',
            PayoutOperationType::MARK_SENT => 'Отметка об отправке',
            PayoutOperationType::SET_HOLD => 'Установка холда',
            PayoutOperationType::RELEASE_HOLD => 'Снятие холда',
            PayoutOperationType::CREDIT_TRADER => 'Зачисление трейдеру',
            PayoutOperationType::SERVICE_INCOME => 'Доход сервиса',
            PayoutOperationType::TEAMLEAD_INCOME => 'Доход тимлида',
        };
    }

    private function formatMoney(?Money $money): ?array
    {
        if (! $money) {
            return null;
        }

        return [
            'value' => $money->toBeauty(),
            'currency' => strtoupper($money->getCurrency()->getCode()),
        ];
    }
}

