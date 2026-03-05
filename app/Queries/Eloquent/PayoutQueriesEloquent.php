<?php

namespace App\Queries\Eloquent;

use App\Enums\PayoutStatus;
use App\Models\Payout\Payout;
use App\Models\User;
use App\ObjectValues\TableFilters\TableFiltersValue;
use App\Queries\Interfaces\PayoutQueries;
use App\Services\Money\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PayoutQueriesEloquent implements PayoutQueries
{
    private const ACTIVE_STATUSES = [
        PayoutStatus::TAKEN,
        PayoutStatus::SENT,
    ];

    /**
     * @inheritDoc
     */
    public function getStackForTrader(): Collection
    {
        return $this->baseQuery()
            ->where('status', PayoutStatus::OPEN->value)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function getActiveForTrader(User $trader): Collection
    {
        return $this->baseQuery()
            ->where('trader_id', $trader->id)
            ->whereIn('status', $this->statusValues(self::ACTIVE_STATUSES))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [PayoutStatus::TAKEN->value])
            ->orderBy('taken_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function paginateHistoryForTrader(User $trader, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->where('trader_id', $trader->id)
            ->whereIn('status', $this->statusValues([
                PayoutStatus::COMPLETED,
                PayoutStatus::CANCELED,
            ]))
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'history_page');
    }

    public function countActiveForTrader(User $trader): int
    {
        return Payout::query()
            ->where('trader_id', $trader->id)
            ->whereIn('status', $this->statusValues(self::ACTIVE_STATUSES))
            ->count();
    }

    public function paginateForAdmin(TableFiltersValue $filters): LengthAwarePaginator
    {
        $currency = $filters->currency ?? 'RUB';

        return $this->baseQuery()
            ->with([
                'operations' => static fn ($query) => $query
                    ->select(['id', 'payout_id', 'type', 'amount', 'amount_currency', 'meta', 'created_at'])
                    ->orderBy('id'),
            ])
            ->when(! empty($filters->payoutStatuses), function (Builder $query) use ($filters) {
                $query->whereIn('status', $filters->payoutStatuses);
            })
            ->when(! empty($filters->payoutMethodTypes), function (Builder $query) use ($filters) {
                $query->whereIn('payout_method_type', $filters->payoutMethodTypes);
            })
            ->when($filters->startDate, function (Builder $query) use ($filters) {
                $query->whereDate('created_at', '>=', $filters->startDate);
            })
            ->when($filters->endDate, function (Builder $query) use ($filters) {
                $query->whereDate('created_at', '<=', $filters->endDate);
            })
            ->when($filters->uuid, function (Builder $query) use ($filters) {
                $query->where('uuid', 'LIKE', '%' . $filters->uuid . '%');
            })
            ->when($filters->externalID, function (Builder $query) use ($filters) {
                $query->where('external_id', 'LIKE', '%' . $filters->externalID . '%');
            })
            ->when($filters->paymentDetail, function (Builder $query) use ($filters) {
                $query->where('requisites', 'LIKE', '%' . $filters->paymentDetail . '%');
            })
            ->when($filters->merchant, function (Builder $query) use ($filters) {
                $query->whereRelation('merchant', 'name', 'LIKE', '%' . $filters->merchant . '%');
            })
            ->when($filters->merchantIds, function (Builder $query) use ($filters) {
                $query->whereIn('merchant_id', $filters->merchantIds);
            })
            ->when($filters->user, function (Builder $query) use ($filters) {
                $query->where(function (Builder $relation) use ($filters) {
                    $relation->whereRelation('trader', 'name', 'LIKE', '%' . $filters->user . '%')
                        ->orWhereRelation('trader', 'email', 'LIKE', '%' . $filters->user . '%');
                });
            })
            ->when($filters->paymentGateway, function (Builder $query) use ($filters) {
                $query->where(function (Builder $relation) use ($filters) {
                    $relation->whereRelation('paymentGateway', 'name', 'LIKE', '%' . $filters->paymentGateway . '%')
                        ->orWhereRelation('paymentGateway', 'code', 'LIKE', '%' . $filters->paymentGateway . '%')
                        ->orWhere('bank_name', 'LIKE', '%' . $filters->paymentGateway . '%');
                });
            })
            ->when($filters->amount, function (Builder $query) use ($filters, $currency) {
                $amountUnits = $this->asFiatUnits($filters->amount, $currency);
                if ($amountUnits !== null) {
                    $query->where('amount_fiat', $amountUnits);
                }
            })
            ->when($filters->minAmount, function (Builder $query) use ($filters, $currency) {
                $minUnits = $this->asFiatUnits($filters->minAmount, $currency);
                if ($minUnits !== null) {
                    $query->where('amount_fiat', '>=', $minUnits);
                }
            })
            ->when($filters->maxAmount, function (Builder $query) use ($filters, $currency) {
                $maxUnits = $this->asFiatUnits($filters->maxAmount, $currency);
                if ($maxUnits !== null) {
                    $query->where('amount_fiat', '<=', $maxUnits);
                }
            })
            ->orderByDesc('id')
            ->paginate(request()->integer('per_page', 10));
    }

    public function paginateForMerchant(User $user, TableFiltersValue $filters): LengthAwarePaginator
    {
        $currency = $filters->currency ?? 'RUB';

        return $this->baseQuery()
            ->with([
                'operations' => static fn ($query) => $query
                    ->select(['id', 'payout_id', 'type', 'amount', 'amount_currency', 'meta', 'created_at'])
                    ->orderBy('id'),
            ])
            ->whereRelation('merchant', 'user_id', $user->id)
            ->when($filters->merchantIds, function (Builder $query) use ($filters) {
                $query->whereIn('merchant_id', $filters->merchantIds);
            })
            ->when(! empty($filters->payoutStatuses), function (Builder $query) use ($filters) {
                $query->whereIn('status', $filters->payoutStatuses);
            })
            ->when(! empty($filters->payoutMethodTypes), function (Builder $query) use ($filters) {
                $query->whereIn('payout_method_type', $filters->payoutMethodTypes);
            })
            ->when($filters->startDate, function (Builder $query) use ($filters) {
                $query->whereDate('created_at', '>=', $filters->startDate);
            })
            ->when($filters->endDate, function (Builder $query) use ($filters) {
                $query->whereDate('created_at', '<=', $filters->endDate);
            })
            ->when($filters->uuid, function (Builder $query) use ($filters) {
                $query->where('uuid', 'LIKE', '%' . $filters->uuid . '%');
            })
            ->when($filters->externalID, function (Builder $query) use ($filters) {
                $query->where('external_id', 'LIKE', '%' . $filters->externalID . '%');
            })
            ->when($filters->paymentDetail, function (Builder $query) use ($filters) {
                $query->where('requisites', 'LIKE', '%' . $filters->paymentDetail . '%');
            })
            ->when($filters->paymentGateway, function (Builder $query) use ($filters) {
                $query->where(function (Builder $relation) use ($filters) {
                    $relation->whereRelation('paymentGateway', 'name', 'LIKE', '%' . $filters->paymentGateway . '%')
                        ->orWhereRelation('paymentGateway', 'code', 'LIKE', '%' . $filters->paymentGateway . '%')
                        ->orWhere('bank_name', 'LIKE', '%' . $filters->paymentGateway . '%');
                });
            })
            ->when($filters->amount, function (Builder $query) use ($filters, $currency) {
                $amountUnits = $this->asFiatUnits($filters->amount, $currency);
                if ($amountUnits !== null) {
                    $query->where('amount_fiat', $amountUnits);
                }
            })
            ->when($filters->minAmount, function (Builder $query) use ($filters, $currency) {
                $minUnits = $this->asFiatUnits($filters->minAmount, $currency);
                if ($minUnits !== null) {
                    $query->where('amount_fiat', '>=', $minUnits);
                }
            })
            ->when($filters->maxAmount, function (Builder $query) use ($filters, $currency) {
                $maxUnits = $this->asFiatUnits($filters->maxAmount, $currency);
                if ($maxUnits !== null) {
                    $query->where('amount_fiat', '<=', $maxUnits);
                }
            })
            ->orderByDesc('id')
            ->paginate(request()->integer('per_page', 10));
    }

    private function baseQuery(): Builder
    {
        return Payout::query()
            ->select([
                'id',
                'uuid',
                'external_id',
                'merchant_id',
                'trader_id',
                'payment_gateway_id',
                'bank_name',
                'payout_method_type',
                'requisites',
                'initials',
                'callback_url',
                'amount_fiat',
                'amount_fiat_currency',
                'usdt_body',
                'usdt_body_currency',
                'merchant_debit',
                'merchant_debit_currency',
                'trader_credit',
                'trader_credit_currency',
                'total_fee',
                'total_fee_currency',
                'trader_fee',
                'trader_fee_currency',
                'teamlead_fee',
                'teamlead_fee_currency',
                'service_fee',
                'service_fee_currency',
                'total_commission_rate',
                'trader_commission_rate',
                'teamlead_commission_rate',
                'rate_market',
                'conversion_price',
                'conversion_price_currency',
                'rate_fixed_at',
                'status',
                'taken_at',
                'sent_at',
                'hold_until',
                'completed_at',
                'canceled_at',
                'receipt_path',
                'expires_at',
                'created_at',
                'updated_at',
            ])
            ->with([
                'paymentGateway:id,name,code,logo,currency,reservation_time_for_payouts,trader_commission_rate_for_payouts,total_service_commission_rate_for_payouts',
                'merchant:id,name,user_id',
                'merchant.user:id,name,email',
                'trader:id,name,email,payout_hold_enabled,payout_hold_minutes,payout_active_payouts_limit',
            ]);
    }

    /**
     * @param array<int, PayoutStatus> $statuses
     * @return array<int, string>
     */
    private function statusValues(array $statuses): array
    {
        return array_map(static fn (PayoutStatus $status) => $status->value, $statuses);
    }

    private function asFiatUnits(?string $amount, ?string $currency): ?string
    {
        if ($amount === null) {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], trim((string) $amount));
        $normalized = preg_replace('/[^0-9\.\-]/', '', $normalized);

        if ($normalized === '' || $normalized === '-' || $normalized === '.' || $normalized === '-.') {
            return null;
        }

        $currencyCode = strtoupper($currency ?: 'RUB');

        try {
            return Money::fromPrecision($normalized, $currencyCode)->toUnits();
        } catch (\Throwable) {
            return null;
        }
    }
}


