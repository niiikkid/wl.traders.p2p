<?php

namespace App\DTO\Tron;

use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\Carbon;

/**
 * Normalized view of a single incoming TRC20 transfer read from the blockchain.
 *
 * This DTO carries raw blockchain facts only. It must never own invoice status,
 * balances, or settlement decisions — those stay inside the local system.
 */
class TronTransfer
{
    public function __construct(
        public readonly string $txid,
        public readonly string $from,
        public readonly string $to,
        public readonly Money $amount,
        public readonly string $contract,
        public readonly Carbon $timestamp,
        public readonly ?int $confirmations = null,
    ) {}

    /**
     * Build a transfer from a TronGrid TRC20 transaction row.
     *
     * TRC20 USDT uses 6 decimals; the local money layer uses 8, so the raw
     * integer value must be converted to a decimal string explicitly.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromTrc20Row(array $row): ?self
    {
        $txid = $row['transaction_id'] ?? null;
        $from = $row['from'] ?? null;
        $to = $row['to'] ?? null;
        $rawValue = $row['value'] ?? null;
        $tokenInfo = $row['token_info'] ?? [];
        $contract = is_array($tokenInfo) ? ($tokenInfo['address'] ?? null) : null;
        $decimals = is_array($tokenInfo) ? (int) ($tokenInfo['decimals'] ?? 6) : 6;
        $blockTimestamp = $row['block_timestamp'] ?? null;

        if (! is_string($txid) || ! is_string($from) || ! is_string($to)) {
            return null;
        }

        if (! is_string($rawValue) && ! is_int($rawValue)) {
            return null;
        }

        if (! is_string($contract)) {
            return null;
        }

        $divisor = bcpow('10', (string) $decimals);
        $precisionAmount = bcdiv((string) $rawValue, $divisor === '0' ? '1' : $divisor, 8);

        return new self(
            txid: $txid,
            from: $from,
            to: $to,
            amount: Money::fromPrecision($precisionAmount, Currency::USDT()->getCode()),
            contract: $contract,
            timestamp: is_numeric($blockTimestamp)
                ? Carbon::createFromTimestampMs((int) $blockTimestamp)
                : now(),
            confirmations: null,
        );
    }

    public function withConfirmations(?int $confirmations): self
    {
        return new self(
            txid: $this->txid,
            from: $this->from,
            to: $this->to,
            amount: $this->amount,
            contract: $this->contract,
            timestamp: $this->timestamp,
            confirmations: $confirmations,
        );
    }
}
