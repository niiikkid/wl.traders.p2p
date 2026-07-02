<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\WalletDepositException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\WalletDepositInvoiceResource;
use App\Models\WalletDepositInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WalletDepositInvoiceController extends Controller
{
    public function transfers(WalletDepositInvoice $walletDepositInvoice): JsonResponse
    {
        try {
            return response()->json([
                'transfers' => services()->walletDeposit()->addressTransfersForReview($walletDepositInvoice),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Wallet deposit transfers lookup failed', [
                'invoice_id' => $walletDepositInvoice->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Не удалось получить транзакции из блокчейна. Попробуйте позже.'], 502);
        }
    }

    public function manualAttach(Request $request, WalletDepositInvoice $walletDepositInvoice): JsonResponse
    {
        $validated = $request->validate([
            'txid' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $invoice = services()->walletDeposit()->manualAttach(
                invoice: $walletDepositInvoice,
                txid: $validated['txid'],
                admin: $request->user(),
                note: $validated['note'] ?? null,
            );

            return response()->json([
                'invoice' => (new WalletDepositInvoiceResource($invoice->loadMissing(['wallet.user', 'resolvedBy'])))->resolve(),
            ]);
        } catch (WalletDepositException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Wallet deposit manual attach failed', [
                'invoice_id' => $walletDepositInvoice->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Не удалось привязать транзакцию. Попробуйте позже.'], 502);
        }
    }
}
