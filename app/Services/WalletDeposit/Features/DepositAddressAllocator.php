<?php

namespace App\Services\WalletDeposit\Features;

use App\Enums\NetworkEnum;
use App\Enums\WalletDepositInvoiceStatus;
use App\Exceptions\WalletDepositException;
use App\Models\WalletDepositAddress;
use App\Models\WalletDepositInvoice;
use App\Services\Money\Money;

/**
 * Picks an active pool address that can receive a new invoice without an
 * amount collision against other active invoices on the same address.
 *
 * Must be called inside a database transaction: it locks the candidate rows
 * and the colliding invoice rows so concurrent creates cannot pick clashing
 * amounts on the same address.
 */
class DepositAddressAllocator
{
    public const CURRENCY = 'USDT';

    public function allocate(Money $amount): WalletDepositAddress
    {
        [$lowerUnits, $upperUnits] = $this->collisionWindow($amount);

        $candidates = WalletDepositAddress::query()
            ->active()
            ->where('currency', self::CURRENCY)
            ->where('network', NetworkEnum::TRX->value)
            ->withCount(['invoices as open_invoices_count' => function ($query): void {
                $query->whereIn('status', $this->activeStatuses())
                    ->where('expires_at', '>', now());
            }])
            ->orderBy('open_invoices_count')
            ->orderByRaw('last_checked_at IS NULL DESC')
            ->orderBy('last_checked_at')
            ->orderBy('id')
            ->get();

        foreach ($candidates as $candidate) {
            $locked = WalletDepositAddress::query()
                ->whereKey($candidate->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! $locked->is_active) {
                continue;
            }

            $hasCollision = WalletDepositInvoice::query()
                ->where('deposit_address_id', $locked->id)
                ->whereIn('status', $this->activeStatuses())
                ->where('expires_at', '>', now())
                ->whereRaw('CAST(amount AS DECIMAL(65,0)) BETWEEN ? AND ?', [$lowerUnits, $upperUnits])
                ->lockForUpdate()
                ->exists();

            if (! $hasCollision) {
                return $locked;
            }
        }

        throw WalletDepositException::noActiveAddressAvailable();
    }

    /**
     * @return array{0: string, 1: string} inclusive [lowerUnits, upperUnits]
     */
    private function collisionWindow(Money $amount): array
    {
        $percent = (string) config('services.wallet_deposit.amount_collision_percent', 5);
        $factor = bcdiv($percent, '100', 8);

        $lower = $amount->mul(bcsub('1', $factor, 8));
        $upper = $amount->mul(bcadd('1', $factor, 8));

        return [$lower->toUnits(), $upper->toUnits()];
    }

    /**
     * @return list<string>
     */
    private function activeStatuses(): array
    {
        return [
            WalletDepositInvoiceStatus::PENDING->value,
            WalletDepositInvoiceStatus::PROCESSING->value,
        ];
    }
}
