<?php

namespace App\Http\Controllers;

use App\Http\Resources\WalletDepositInvoiceResource;
use App\Models\WalletDepositInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class WalletDepositInvoiceController extends Controller
{
    public function show(WalletDepositInvoice $walletDepositInvoice): JsonResponse
    {
        Gate::authorize('access-to-wallet-deposit-invoice', $walletDepositInvoice);

        $walletDepositInvoice->loadMissing('wallet');

        return response()->json([
            'invoice' => (new WalletDepositInvoiceResource($walletDepositInvoice))->resolve(),
        ]);
    }

    public function qr(WalletDepositInvoice $walletDepositInvoice): Response
    {
        Gate::authorize('access-to-wallet-deposit-invoice', $walletDepositInvoice);

        abort_unless(
            $walletDepositInvoice->qr_disk && $walletDepositInvoice->qr_path,
            404
        );

        $disk = Storage::disk($walletDepositInvoice->qr_disk);

        abort_unless($disk->exists($walletDepositInvoice->qr_path), 404);

        return response($disk->get($walletDepositInvoice->qr_path), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
