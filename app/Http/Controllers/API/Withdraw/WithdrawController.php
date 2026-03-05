<?php

namespace App\Http\Controllers\API\Withdraw;

use App\Exceptions\InvoiceException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WithdrawController extends Controller
{
    public function webhook(Request $request)
    {
        $request->validate([
            'payment_id' => ['required', 'integer', 'exists:invoices,id'],
            'tx_hash' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', 'in:success,fail'],
        ]);

        try {
            $invoice = services()->invoice()->finishAutoWithdrawal(
                paymentID: $request->payment_id,
                status: $request->status,
                txHash: $request->tx_hash,
            );

            return response()->success([
                'payment_id' => $invoice->id,
                'tx_hash' => $invoice->tx_hash,
                'status' => $invoice->status->value,
            ]);
        } catch (InvoiceException $e) {
            return response()->failWithMessage($e->getMessage());
        }
    }
}
