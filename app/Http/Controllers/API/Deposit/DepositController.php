<?php

namespace App\Http\Controllers\API\Deposit;

use App\Enums\BalanceType;
use App\Exceptions\InvoiceException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DepositController extends Controller
{
    public function webhook(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'amount' => ['required', 'numeric', 'min:1'],
            'transaction_id' => ['required', 'string'],
            'tx_hash' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = User::where('email', $request->email)->first();

        try {
            services()->invoice()->deposit(
                walletID: $user->wallet->id,
                amount: Money::fromPrecision($request->amount, Currency::USDT()),
                balanceType: BalanceType::TRUST,
                transactionID: $request->transaction_id,
                txHash: $request->tx_hash,
            );

            return response()->success();
        } catch (InvoiceException $e) {
            return response()->failWithMessage($e->getMessage());
        }
    }

    public function externalWebhook(Request $request)
    {
        $event = $request->header('X-Callback-Event');
        $payload = $request->all();

        $invoiceData = $payload['invoice'] ?? null;
        if (! is_array($invoiceData)) {
            return response()->json(['message' => 'Invalid payload'], 422);
        }

        $externalInvoiceId = $invoiceData['external_invoice_id'] ?? null;
        $status = $invoiceData['status'] ?? null;
        $txid = $invoiceData['txid'] ?? null;
        $amountReceived = $invoiceData['amount_received'] ?? null;

        if (! $externalInvoiceId || ! $status) {
            return response()->json(['message' => 'Invalid invoice data'], 422);
        }

        try {
            // Завершаем или отменяем локальный инвойс по статусу внешнего
            if ($status === 'paid') {
                services()->invoice()->finishExternalDeposit(
                    invoiceID: (int) $externalInvoiceId,
                    amountReceived: $amountReceived ? Money::fromPrecision($amountReceived, Currency::USDT()) : null,
                    txHash: $txid,
                );
            } elseif (in_array($status, ['expired', 'cancelled'])) {
                services()->invoice()->cancelExternalDeposit((int) $externalInvoiceId);
            }

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('External invoice callback error', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            return response()->json(['message' => 'Callback processing error'], 500);
        }
    }
}
