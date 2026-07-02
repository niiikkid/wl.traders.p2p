<?php

namespace App\Services\Blockchain;

use App\DTO\Tron\TronTransfer;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin read-only client for the TRON blockchain via TronGrid.
 *
 * Responsibilities are limited to returning normalized blockchain facts:
 * incoming USDT/TRC20 transfers, confirmation counts, and address balance.
 * It never decides invoice status or credits balances.
 */
class TronGridClient
{
    private string $baseUrl;

    private ?string $apiKey;

    private string $usdtContract;

    private string $tronscanBaseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.trongrid.base_url'), '/');
        $this->apiKey = config('services.trongrid.api_key');
        $this->usdtContract = (string) config('services.trongrid.usdt_contract');
        $this->tronscanBaseUrl = rtrim((string) config('services.trongrid.tronscan_base_url'), '/');
    }

    /**
     * List confirmed incoming USDT/TRC20 transfers for an address, newest first.
     *
     * @return list<TronTransfer>
     */
    public function incomingUsdtTransfers(string $address, ?Carbon $minTimestamp = null, int $limit = 50): array
    {
        $query = [
            'only_confirmed' => 'true',
            'only_to' => 'true',
            'limit' => max(1, min($limit, 200)),
            'contract_address' => $this->usdtContract,
            'order_by' => 'block_timestamp,desc',
        ];

        if ($minTimestamp) {
            $query['min_timestamp'] = $minTimestamp->getTimestampMs();
        }

        $response = $this->http()->get("{$this->baseUrl}/v1/accounts/{$address}/transactions/trc20", $query);

        if ($response->failed()) {
            $this->logFailure('trc20_transfers', $response->status());

            throw new RuntimeException('trongrid_request_failed');
        }

        $rows = $response->json('data');

        if (! is_array($rows)) {
            return [];
        }

        $transfers = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (($row['type'] ?? null) !== 'Transfer') {
                continue;
            }

            $transfer = TronTransfer::fromTrc20Row($row);

            if ($transfer === null) {
                continue;
            }

            // Defense in depth: enforce token contract and recipient locally.
            if (! $this->contractMatches($transfer->contract) || $transfer->to !== $address) {
                continue;
            }

            $transfers[] = $transfer;
        }

        return $transfers;
    }

    /**
     * Fetch a single incoming USDT transfer for an address by transaction hash (fresh, no cache).
     * Confirmations are attached from the current chain head.
     */
    public function findAddressTransfer(string $address, string $txid): ?TronTransfer
    {
        foreach ($this->incomingUsdtTransfers($address, null, 200) as $transfer) {
            if ($transfer->txid === $txid) {
                return $transfer->withConfirmations($this->confirmationsFor($txid));
            }
        }

        return null;
    }

    /**
     * Confirmation count for a transaction, or null when it cannot be determined yet.
     */
    public function confirmationsFor(string $txid): ?int
    {
        $infoResponse = $this->http()->post("{$this->baseUrl}/wallet/gettransactioninfobyid", [
            'value' => $txid,
        ]);

        if ($infoResponse->failed()) {
            $this->logFailure('transaction_info', $infoResponse->status());

            throw new RuntimeException('trongrid_request_failed');
        }

        $blockNumber = $infoResponse->json('blockNumber');

        if (! is_numeric($blockNumber)) {
            return null;
        }

        $nowResponse = $this->http()->post("{$this->baseUrl}/wallet/getnowblock");

        if ($nowResponse->failed()) {
            $this->logFailure('now_block', $nowResponse->status());

            throw new RuntimeException('trongrid_request_failed');
        }

        $headBlock = $nowResponse->json('block_header.raw_data.number');

        if (! is_numeric($headBlock)) {
            return null;
        }

        return max(0, ((int) $headBlock) - ((int) $blockNumber) + 1);
    }

    /**
     * Last known on-chain USDT balance for an address (admin diagnostics only).
     */
    public function getAddressUsdtBalance(string $address): Money
    {
        $response = $this->http()->get("{$this->baseUrl}/v1/accounts/{$address}");

        if ($response->failed()) {
            $this->logFailure('account', $response->status());

            throw new RuntimeException('trongrid_request_failed');
        }

        $balances = $response->json('data.0.trc20');

        if (is_array($balances)) {
            foreach ($balances as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                foreach ($entry as $contract => $rawValue) {
                    if ($this->contractMatches((string) $contract)) {
                        $precision = bcdiv((string) $rawValue, '1000000', 8);

                        return Money::fromPrecision($precision, Currency::USDT()->getCode());
                    }
                }
            }
        }

        return Money::zero(Currency::USDT()->getCode());
    }

    public function explorerUrl(string $txid): string
    {
        return "{$this->tronscanBaseUrl}/#/transaction/{$txid}";
    }

    private function http(): PendingRequest
    {
        $request = Http::acceptJson()
            ->timeout(15)
            ->connectTimeout(5);

        if (is_string($this->apiKey) && $this->apiKey !== '') {
            $request = $request->withHeaders(['TRON-PRO-API-KEY' => $this->apiKey]);
        }

        return $request;
    }

    private function contractMatches(?string $contract): bool
    {
        return is_string($contract) && $contract === $this->usdtContract;
    }

    private function logFailure(string $operation, int $status): void
    {
        Log::warning('TronGrid request failed', [
            'operation' => $operation,
            'status' => $status,
        ]);
    }
}
